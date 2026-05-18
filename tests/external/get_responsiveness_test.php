<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for the block_grade_me_get_responsiveness external function.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\external;

use block_grade_me\local\sla\group_resolver;
use block_grade_me\local\sla\rollup_service;
use block_grade_me\local\sla\submission_ledger;
use externallib_advanced_testcase;
use required_capability_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * @group block_grade_me
 * @covers \block_grade_me\external\get_responsiveness
 */
class get_responsiveness_test extends externallib_advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        group_resolver::reset_cache();
    }

    /**
     * Build a course with a teacher and one group containing two students,
     * each with one submitted-but-ungraded assignment submission.
     *
     * @return array
     */
    private function build_scenario(): array {
        global $DB;
        $this->resetAfterTest();

        $g = $this->getDataGenerator();
        $course = $g->create_course();
        $teacher = $g->create_user();
        $student1 = $g->create_user();
        $student2 = $g->create_user();
        $g->enrol_user($teacher->id, $course->id, 'editingteacher');
        $g->enrol_user($student1->id, $course->id, 'student');
        $g->enrol_user($student2->id, $course->id, 'student');

        $group = $g->create_group(['courseid' => $course->id]);
        // The teacher needs to be in the group for groups_get_user_groups to return it.
        $g->create_group_member(['groupid' => $group->id, 'userid' => $teacher->id]);
        $g->create_group_member(['groupid' => $group->id, 'userid' => $student1->id]);
        $g->create_group_member(['groupid' => $group->id, 'userid' => $student2->id]);

        $assign = $g->get_plugin_generator('mod_assign')->create_instance([
            'course'      => $course->id,
            'name'        => 'WS responsiveness fixture',
            'grade'       => 100,
            'maxattempts' => -1,
            'duedate'     => time() + 5 * 24 * 3600,
        ]);
        $cmid = (int) $assign->cmid;

        foreach ([$student1, $student2] as $student) {
            $DB->insert_record('assign_submission', (object) [
                'assignment'    => $assign->id,
                'userid'        => $student->id,
                'timecreated'   => time() - 30 * 3600,
                'timemodified'  => time() - 30 * 3600,
                'status'        => 'submitted',
                'attemptnumber' => 0,
                'latest'        => 1,
                'groupid'       => 0,
            ]);
            submission_ledger::upsert_for_cm_user_attempt($cmid, (int) $student->id, 0);
        }

        rollup_service::recompute_group((int) $course->id, (int) $group->id, 'assign');

        return [
            'course'   => $course,
            'teacher'  => $teacher,
            'group'    => $group,
            'assign'   => $assign,
            'cmid'     => $cmid,
        ];
    }

    public function test_response_shape_for_teacher(): void {
        $s = $this->build_scenario();
        $this->setUser($s['teacher']);

        $response = get_responsiveness::execute((int) $s['course']->id);
        // Validate via the declared return structure too.
        $response = \core_external\external_api::clean_returnvalue(
            get_responsiveness::execute_returns(),
            $response
        );

        $this->assertTrue($response['success']);
        $this->assertSame((int) $s['course']->id, $response['courseid']);
        $this->assertCount(1, $response['groups']);

        $group = $response['groups'][0];
        $this->assertSame((int) $s['group']->id, $group['groupid']);
        $this->assertSame(2, $group['pending']);
        $this->assertGreaterThan(0, $group['median_h']);
        $this->assertNotEmpty($group['activities']);
        $this->assertSame((int) $s['cmid'], $group['activities'][0]['cmid']);
    }

    public function test_capability_required(): void {
        $s = $this->build_scenario();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($other);

        $this->expectException(required_capability_exception::class);
        get_responsiveness::execute((int) $s['course']->id);
    }

    public function test_compare_gated_by_school_capability(): void {
        global $DB;
        $s = $this->build_scenario();
        // Seed a site_stats row so compare has something to read.
        $DB->insert_record('block_grade_me_sla_site', (object) [
            'modtype'      => 'assign',
            'day'          => (int) date('Ymd'),
            'medianh'      => 30.0,
            'p10h'         => 5.0,
            'numgraded'    => 100,
            'timemodified' => time(),
        ]);

        // Editingteacher gets viewschoolaverage at SYSTEM level by archetype.
        $this->setUser($s['teacher']);
        $response = get_responsiveness::execute((int) $s['course']->id);
        $this->assertNotNull($response['groups'][0]['compare']);
        $this->assertEqualsWithDelta(30.0, $response['groups'][0]['compare']['school_median'], 0.01);

        // Drop the capability for this teacher and re-fetch.
        $rolecontext = \context_system::instance();
        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability(
            'block/grade_me:viewschoolaverage',
            CAP_PROHIBIT,
            $teacherroleid,
            $rolecontext->id,
            true
        );
        // Force cache miss for this second call.
        $cache = \cache::make('block_grade_me', 'responsiveness_payload');
        $cache->purge();

        $response = get_responsiveness::execute((int) $s['course']->id);
        $this->assertNull($response['groups'][0]['compare']);
    }

    public function test_cache_hit_returns_same_payload(): void {
        $s = $this->build_scenario();
        $this->setUser($s['teacher']);
        $first = get_responsiveness::execute((int) $s['course']->id);
        $second = get_responsiveness::execute((int) $s['course']->id);
        $this->assertSame($first['lastsynced'], $second['lastsynced']);
    }

    public function test_no_groups_returns_empty_groups(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $response = get_responsiveness::execute((int) $course->id);
        $this->assertTrue($response['success']);
        $this->assertSame([], $response['groups']);
    }
}

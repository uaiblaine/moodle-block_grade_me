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
 * Tests for the SLA event observer.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\local\sla;

use advanced_testcase;
use context_module;

defined('MOODLE_INTERNAL') || die();

/**
 * @group block_grade_me
 * @covers \block_grade_me\local\sla\observer
 */
class observer_test extends advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        group_resolver::reset_cache();
    }

    /**
     * Seed a complete scenario: course, group, student, teacher, one assign
     * with one submission. Optionally with a grade row.
     *
     * @param array $opts
     * @return array
     */
    private function build_scenario(array $opts = []): array {
        global $DB;
        $this->resetAfterTest();

        $defaults = [
            'submissiontime' => time() - 7200,
            'gradetime'      => null,
            'gradevalue'     => null,
            'attemptnumber'  => 0,
            'duedate'        => 0,
        ];
        $opts = $opts + $defaults;

        $g = $this->getDataGenerator();
        $course = $g->create_course();
        $student = $g->create_user();
        $teacher = $g->create_user();
        $g->enrol_user($student->id, $course->id, 'student');
        $g->enrol_user($teacher->id, $course->id, 'editingteacher');

        $group = $g->create_group(['courseid' => $course->id]);
        $g->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);

        $assign = $g->get_plugin_generator('mod_assign')->create_instance([
            'course'      => $course->id,
            'name'        => 'Observer fixture',
            'grade'       => 100,
            'maxattempts' => -1,
            'duedate'     => $opts['duedate'],
        ]);

        $subid = $DB->insert_record('assign_submission', (object) [
            'assignment'    => $assign->id,
            'userid'        => $student->id,
            'timecreated'   => $opts['submissiontime'],
            'timemodified'  => $opts['submissiontime'],
            'status'        => 'submitted',
            'attemptnumber' => $opts['attemptnumber'],
            'latest'        => 1,
            'groupid'       => 0,
        ]);

        $gradeid = null;
        if ($opts['gradetime'] !== null) {
            $gradeid = $DB->insert_record('assign_grades', (object) [
                'assignment'    => $assign->id,
                'userid'        => $student->id,
                'timecreated'   => $opts['gradetime'],
                'timemodified'  => $opts['gradetime'],
                'grader'        => $teacher->id,
                'grade'         => $opts['gradevalue'] ?? 80.0,
                'attemptnumber' => $opts['attemptnumber'],
            ]);
        }

        return [
            'course'       => $course,
            'student'      => $student,
            'teacher'      => $teacher,
            'group'        => $group,
            'assign'       => $assign,
            'cmid'         => (int) $assign->cmid,
            'submissionid' => $subid,
            'gradeid'      => $gradeid,
        ];
    }

    public function test_submission_changed_creates_ledger_row(): void {
        global $DB;
        $s = $this->build_scenario();
        $event = \mod_assign\event\assessable_submitted::create([
            'context'  => context_module::instance($s['cmid']),
            'objectid' => $s['submissionid'],
            'userid'   => (int) $s['student']->id,
            'other'    => ['submission_editable' => false],
        ]);
        observer::submission_changed($event);

        $row = $DB->get_record('block_grade_me_sla_sub', [
            'cmid' => $s['cmid'], 'userid' => $s['student']->id, 'attemptnumber' => 0,
        ]);
        $this->assertNotEmpty($row);
        $this->assertSame('submitted', $row->submissionstatus);
        $this->assertSame((int) $s['group']->id, (int) $row->groupid);
    }

    public function test_submission_changed_is_idempotent(): void {
        global $DB;
        $s = $this->build_scenario();
        $event = \mod_assign\event\assessable_submitted::create([
            'context'  => context_module::instance($s['cmid']),
            'objectid' => $s['submissionid'],
            'userid'   => (int) $s['student']->id,
            'other'    => ['submission_editable' => false],
        ]);
        observer::submission_changed($event);
        observer::submission_changed($event);

        $count = $DB->count_records('block_grade_me_sla_sub', [
            'cmid' => $s['cmid'], 'userid' => $s['student']->id,
        ]);
        $this->assertSame(1, $count);

        $queuecount = $DB->count_records('block_grade_me_sla_queue', [
            'courseid' => $s['course']->id, 'groupid' => $s['group']->id, 'modtype' => 'assign',
        ]);
        $this->assertSame(1, $queuecount);
    }

    public function test_submission_changed_ignores_unknown_submission(): void {
        global $DB;
        $s = $this->build_scenario();
        $event = \mod_assign\event\assessable_submitted::create([
            'context'  => context_module::instance($s['cmid']),
            'objectid' => 99999999,
            'userid'   => (int) $s['student']->id,
            'other'    => ['submission_editable' => false],
        ]);
        observer::submission_changed($event);

        $this->assertSame(0, $DB->count_records('block_grade_me_sla_sub'));
    }

    public function test_submission_graded_sets_timegraded(): void {
        global $DB;
        $submitted = time() - 5 * 3600;
        $graded = $submitted + 4 * 3600;
        $s = $this->build_scenario([
            'submissiontime' => $submitted,
            'gradetime'      => $graded,
        ]);
        $event = \mod_assign\event\submission_graded::create([
            'context'       => context_module::instance($s['cmid']),
            'objectid'      => $s['gradeid'],
            'relateduserid' => (int) $s['student']->id,
            'other'         => ['assignid' => (int) $s['assign']->id],
        ]);
        observer::submission_graded($event);

        $row = $DB->get_record('block_grade_me_sla_sub', [
            'cmid' => $s['cmid'], 'userid' => $s['student']->id, 'attemptnumber' => 0,
        ]);
        $this->assertSame($graded, (int) $row->timegraded);
        $this->assertEqualsWithDelta(4.0, (float) $row->waitinghours, 0.01);
    }

    public function test_submission_graded_ignores_missing_grade(): void {
        global $DB;
        $s = $this->build_scenario();
        // Build the event with a fake grade id.
        $event = \mod_assign\event\submission_graded::create([
            'context'       => context_module::instance($s['cmid']),
            'objectid'      => 99999999,
            'relateduserid' => (int) $s['student']->id,
            'other'         => ['assignid' => (int) $s['assign']->id],
        ]);
        observer::submission_graded($event);
        $this->assertSame(0, $DB->count_records('block_grade_me_sla_sub'));
    }

    public function test_override_changed_re_resolves_rule(): void {
        global $DB;
        $opens = time() - 3600;
        $closes = time() + 5 * 24 * 3600;
        $s = $this->build_scenario(['duedate' => $closes]);

        // First, seed a ledger row.
        submission_ledger::upsert_for_cm_user_attempt(
            $s['cmid'],
            (int) $s['student']->id,
            0
        );

        // Now create a group override that pushes the close date.
        $newclose = $closes + 10 * 24 * 3600;
        $overrideid = $DB->insert_record('assign_overrides', (object) [
            'assignid' => $s['assign']->id,
            'groupid'  => $s['group']->id,
            'userid'   => null,
            'sortorder' => 1,
            'duedate'  => $newclose,
        ]);

        $event = \mod_assign\event\group_override_created::create([
            'context'  => context_module::instance($s['cmid']),
            'objectid' => $overrideid,
            'other'    => [
                'assignid' => (int) $s['assign']->id,
                'groupid'  => (int) $s['group']->id,
            ],
        ]);
        observer::override_changed($event);

        $row = $DB->get_record('block_grade_me_sla_sub', [
            'cmid' => $s['cmid'], 'userid' => $s['student']->id, 'attemptnumber' => 0,
        ]);
        $this->assertSame($newclose, (int) $row->timecloses);
    }

    public function test_course_module_deleted_clears_ledger_for_assign(): void {
        global $DB;
        $s = $this->build_scenario();
        submission_ledger::upsert_for_cm_user_attempt($s['cmid'], (int) $s['student']->id, 0);

        $event = \core\event\course_module_deleted::create([
            'context'      => context_module::instance($s['cmid']),
            'objectid'     => $s['cmid'],
            'courseid'     => (int) $s['course']->id,
            'other'        => [
                'modulename'   => 'assign',
                'instanceid'   => (int) $s['assign']->id,
                'name'         => 'Observer fixture',
            ],
        ]);
        observer::course_module_deleted($event);

        $this->assertSame(0, $DB->count_records('block_grade_me_sla_sub', ['cmid' => $s['cmid']]));
    }

    public function test_course_module_deleted_ignores_non_assign(): void {
        global $DB;
        $s = $this->build_scenario();
        submission_ledger::upsert_for_cm_user_attempt($s['cmid'], (int) $s['student']->id, 0);
        $countbefore = $DB->count_records('block_grade_me_sla_sub');

        // Construct an event with modulename = quiz; do NOT trigger to avoid
        // resolving a real context that wouldn't match.
        $event = \core\event\course_module_deleted::create([
            'context'      => context_module::instance($s['cmid']),
            'objectid'     => $s['cmid'],
            'courseid'     => (int) $s['course']->id,
            'other'        => [
                'modulename'   => 'quiz',
                'instanceid'   => 12345,
                'name'         => 'quiz fixture',
            ],
        ]);
        observer::course_module_deleted($event);

        $this->assertSame($countbefore, $DB->count_records('block_grade_me_sla_sub'));
    }

    public function test_course_deleted_clears_everything(): void {
        global $DB;
        $s = $this->build_scenario();
        submission_ledger::upsert_for_cm_user_attempt($s['cmid'], (int) $s['student']->id, 0);

        $event = \core\event\course_deleted::create([
            'context'  => \context_system::instance(),
            'objectid' => (int) $s['course']->id,
            'other'    => [
                'shortname' => $s['course']->shortname,
                'fullname'  => $s['course']->fullname,
                'idnumber'  => '',
            ],
        ]);
        observer::course_deleted($event);

        $this->assertSame(0, $DB->count_records('block_grade_me_sla_sub', ['courseid' => $s['course']->id]));
        $this->assertSame(0, $DB->count_records('block_grade_me_sla_queue', ['courseid' => $s['course']->id]));
    }
}

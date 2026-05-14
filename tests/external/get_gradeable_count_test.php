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
 * Tests for the block_grade_me_get_gradeable_count external function.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\external;

use context_course;
use core_external\external_api;
use externallib_advanced_testcase;
use invalid_parameter_exception;
use required_capability_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/grade_me/lib.php');
require_once($CFG->dirroot . '/blocks/grade_me/plugins/assign/assign_plugin.php');
require_once($CFG->libdir . '/externallib.php');

/**
 * @group block_grade_me
 * @covers \block_grade_me\external\get_gradeable_count
 */
class get_gradeable_count_test extends externallib_advanced_testcase {

    /**
     * Build a course with a teacher, a student, one assign instance, and one
     * submitted-but-ungraded submission. Returns the relevant objects.
     *
     * @return array{course: stdClass, teacher: stdClass, student: stdClass,
     *               assign: \mod_assign\local\test_helper|stdClass, cmid: int}
     */
    protected function create_assign_scenario(): array {
        global $DB;

        set_config('block_grade_me_enableassign', 1);
        set_config('block_grade_me_maxage', 0);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        $teacher = $generator->create_user();
        $student = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $generator->enrol_user($student->id, $course->id, 'student');

        $assigngen = $generator->get_plugin_generator('mod_assign');
        $assign = $assigngen->create_instance([
            'course'      => $course->id,
            'name'        => 'WS test assignment',
            'grade'       => 100,
            'maxattempts' => 1,
        ]);
        $cmid = (int) $assign->cmid;

        // Submit as student.
        $DB->insert_record('assign_submission', (object) [
            'assignment'    => $assign->id,
            'userid'        => $student->id,
            'timecreated'   => time(),
            'timemodified'  => time(),
            'status'        => 'submitted',
            'groupid'       => 0,
            'attemptnumber' => 0,
            'latest'        => 1,
        ]);

        // Seed the {block_grade_me} cache row for this instance so the WS can resolve it.
        $sortorder = $DB->get_field('grade_items', 'sortorder', [
            'courseid' => $course->id, 'iteminstance' => $assign->id, 'itemmodule' => 'assign',
        ]) ?: 1;
        $DB->insert_record('block_grade_me', (object) [
            'itemname'       => 'WS test assignment',
            'itemtype'       => 'mod',
            'itemmodule'     => 'assign',
            'iteminstance'   => $assign->id,
            'itemsortorder'  => $sortorder,
            'courseid'       => $course->id,
            'coursename'     => $course->shortname,
            'coursemoduleid' => $cmid,
        ]);

        return [
            'course'  => $course,
            'teacher' => $teacher,
            'student' => $student,
            'assign'  => $assign,
            'cmid'    => $cmid,
        ];
    }

    /**
     * Happy path: teacher with mod/assign:grade gets the expected payload.
     */
    public function test_returns_count_and_submissions_for_grader(): void {
        $this->resetAfterTest(true);
        $s = $this->create_assign_scenario();
        $this->setUser($s['teacher']);

        $result = get_gradeable_count::execute($s['course']->id, 'assign');
        $result = external_api::clean_returnvalue(get_gradeable_count::execute_returns(), $result);

        $this->assertTrue($result['success']);
        $this->assertSame($s['course']->id, $result['courseid']);
        $this->assertSame('assign', $result['moduletype']);
        $this->assertSame(1, $result['count']);
        $this->assertCount(1, $result['submissions']);

        $sub = $result['submissions'][0];
        $this->assertSame((int) $s['student']->id, $sub['userid']);
        $this->assertSame($s['cmid'], $sub['coursemoduleid']);
        $this->assertSame('WS test assignment', $sub['itemname']);
        $this->assertStringContainsString(
            '/mod/assign/view.php?id=' . $s['cmid'] . '&action=grade&userid=' . $s['student']->id,
            $sub['gradelink']
        );
    }

    /**
     * A student should get a required_capability_exception.
     */
    public function test_rejects_unprivileged_user(): void {
        $this->resetAfterTest(true);
        $s = $this->create_assign_scenario();
        $this->setUser($s['student']);

        $this->expectException(required_capability_exception::class);
        get_gradeable_count::execute($s['course']->id, 'assign');
    }

    /**
     * Unknown / disabled module types must surface invalid_parameter_exception.
     */
    public function test_rejects_unknown_moduletype(): void {
        $this->resetAfterTest(true);
        $s = $this->create_assign_scenario();
        $this->setUser($s['teacher']);

        $this->expectException(invalid_parameter_exception::class);
        get_gradeable_count::execute($s['course']->id, 'nosuchmod');
    }

    /**
     * The maxage setting should filter out older submissions.
     */
    public function test_respects_maxage_filter(): void {
        global $DB;

        $this->resetAfterTest(true);
        $s = $this->create_assign_scenario();
        $this->setUser($s['teacher']);

        // Backdate the submission to well beyond the maxage window.
        $old = time() - (30 * DAYSECS);
        $DB->execute('UPDATE {assign_submission} SET timecreated = ?, timemodified = ?
                       WHERE assignment = ?', [$old, $old, $s['assign']->id]);

        set_config('block_grade_me_maxage', 1);

        $result = get_gradeable_count::execute($s['course']->id, 'assign');
        $result = external_api::clean_returnvalue(get_gradeable_count::execute_returns(), $result);

        $this->assertSame(0, $result['count']);
        $this->assertSame([], $result['submissions']);
    }

    /**
     * validate_context must reject a courseid outside the user's accessible contexts.
     */
    public function test_validate_context_rejects_other_course(): void {
        $this->resetAfterTest(true);
        $s = $this->create_assign_scenario();

        // Different course where the teacher has no role.
        $othercourse = $this->getDataGenerator()->create_course();

        $this->setUser($s['teacher']);
        $this->expectException(\moodle_exception::class);
        get_gradeable_count::execute($othercourse->id, 'assign');
    }
}

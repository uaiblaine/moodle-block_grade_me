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
 * Tests for the submission ledger service.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\local\sla;

use advanced_testcase;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * @group block_grade_me
 * @covers \block_grade_me\local\sla\submission_ledger
 */
class submission_ledger_test extends advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        group_resolver::reset_cache();
    }

    /**
     * Build a course + teacher + student + one assign with one submission,
     * optionally already graded. Returns the relevant ids.
     *
     * @param array $opts overrides for the scenario
     * @return array
     */
    private function build_scenario(array $opts = []): array {
        global $DB;
        $this->resetAfterTest();

        $defaults = [
            'allowsubmissionsfromdate' => 0,
            'duedate'                  => 0,
            'submissiontime'           => time() - 7200,
            'gradetime'                => null,
            'gradevalue'               => null,
            'attemptnumber'            => 0,
            'submissionstatus'         => 'submitted',
        ];
        $opts = $opts + $defaults;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $teacher = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');

        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);

        $assigngen = $generator->get_plugin_generator('mod_assign');
        $assign = $assigngen->create_instance([
            'course'                   => $course->id,
            'name'                     => 'Ledger fixture',
            'grade'                    => 100,
            'maxattempts'              => -1,
            'allowsubmissionsfromdate' => $opts['allowsubmissionsfromdate'],
            'duedate'                  => $opts['duedate'],
        ]);

        $DB->insert_record('assign_submission', (object) [
            'assignment'    => $assign->id,
            'userid'        => $student->id,
            'timecreated'   => $opts['submissiontime'],
            'timemodified'  => $opts['submissiontime'],
            'status'        => $opts['submissionstatus'],
            'attemptnumber' => $opts['attemptnumber'],
            'latest'        => 1,
            'groupid'       => 0,
        ]);

        if ($opts['gradetime'] !== null) {
            $DB->insert_record('assign_grades', (object) [
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
            'course'  => $course,
            'student' => $student,
            'teacher' => $teacher,
            'group'   => $group,
            'assign'  => $assign,
            'cmid'    => (int) $assign->cmid,
            'opts'    => $opts,
        ];
    }

    public function test_upsert_creates_ledger_row_for_pending_submission(): void {
        global $DB;
        $s = $this->build_scenario();

        $written = submission_ledger::upsert_for_cm_user_attempt($s['cmid'], (int) $s['student']->id, 0);
        $this->assertTrue($written);

        $row = $DB->get_record('block_grade_me_sla_sub', [
            'cmid' => $s['cmid'],
            'userid' => $s['student']->id,
            'attemptnumber' => 0,
        ]);
        $this->assertNotEmpty($row);
        $this->assertSame((int) $s['course']->id, (int) $row->courseid);
        $this->assertSame((int) $s['group']->id, (int) $row->groupid);
        $this->assertSame('assign', $row->modtype);
        $this->assertSame((int) $s['assign']->id, (int) $row->iteminstance);
        $this->assertSame('submitted', $row->submissionstatus);
        $this->assertSame($s['opts']['submissiontime'], (int) $row->timesubmitted);
        $this->assertNull($row->timegraded);
        $this->assertNull($row->waitinghours);
    }

    public function test_upsert_is_idempotent(): void {
        global $DB;
        $s = $this->build_scenario();
        submission_ledger::upsert_for_cm_user_attempt($s['cmid'], (int) $s['student']->id, 0);
        submission_ledger::upsert_for_cm_user_attempt($s['cmid'], (int) $s['student']->id, 0);
        $count = $DB->count_records('block_grade_me_sla_sub', [
            'cmid' => $s['cmid'], 'userid' => $s['student']->id, 'attemptnumber' => 0,
        ]);
        $this->assertSame(1, $count);
    }

    public function test_upsert_no_submission_is_noop(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        $written = submission_ledger::upsert_for_cm_user_attempt((int) $assign->cmid, (int) $user->id, 0);
        $this->assertFalse($written);
        $this->assertSame(0, $DB->count_records('block_grade_me_sla_sub'));
    }

    public function test_upsert_unknown_cm_is_noop(): void {
        $this->resetAfterTest();
        $this->assertFalse(submission_ledger::upsert_for_cm_user_attempt(999999, 1, 0));
    }

    public function test_upsert_sets_timegraded_and_waitinghours(): void {
        global $DB;
        $submitted = time() - 5 * 3600;
        $graded = $submitted + 4 * 3600;  // 4h waiting
        $s = $this->build_scenario([
            'submissiontime' => $submitted,
            'gradetime'      => $graded,
            'gradevalue'     => 75.0,
        ]);

        submission_ledger::upsert_for_cm_user_attempt($s['cmid'], (int) $s['student']->id, 0);

        $row = $DB->get_record('block_grade_me_sla_sub', [
            'cmid' => $s['cmid'], 'userid' => $s['student']->id, 'attemptnumber' => 0,
        ]);
        $this->assertSame($graded, (int) $row->timegraded);
        $this->assertEqualsWithDelta(4.0, (float) $row->waitinghours, 0.01);
    }

    public function test_upsert_ignores_grade_when_submission_newer(): void {
        global $DB;
        // Grade exists but the student re-submitted afterwards — graded should be null.
        $graded = time() - 7200;
        $resubmitted = time() - 3600;
        $s = $this->build_scenario([
            'submissiontime' => $resubmitted,
            'gradetime'      => $graded,
            'gradevalue'     => 75.0,
        ]);

        submission_ledger::upsert_for_cm_user_attempt($s['cmid'], (int) $s['student']->id, 0);

        $row = $DB->get_record('block_grade_me_sla_sub', [
            'cmid' => $s['cmid'], 'userid' => $s['student']->id, 'attemptnumber' => 0,
        ]);
        $this->assertNull($row->timegraded);
        $this->assertNull($row->waitinghours);
    }

    public function test_upsert_ignores_grade_with_negative_value(): void {
        global $DB;
        $s = $this->build_scenario([
            'gradetime'  => time(),
            'gradevalue' => -1.0,  // discarded grade
        ]);

        submission_ledger::upsert_for_cm_user_attempt($s['cmid'], (int) $s['student']->id, 0);

        $row = $DB->get_record('block_grade_me_sla_sub', [
            'cmid' => $s['cmid'], 'userid' => $s['student']->id, 'attemptnumber' => 0,
        ]);
        $this->assertNull($row->timegraded);
    }

    public function test_upsert_enqueues_dirty_tuple(): void {
        global $DB;
        $s = $this->build_scenario();
        submission_ledger::upsert_for_cm_user_attempt($s['cmid'], (int) $s['student']->id, 0);

        $this->assertTrue($DB->record_exists('block_grade_me_sla_queue', [
            'courseid' => $s['course']->id,
            'groupid'  => $s['group']->id,
            'modtype'  => 'assign',
        ]));
    }

    public function test_upsert_captures_assign_dates_as_rule(): void {
        global $DB;
        $opens  = time() - 3600;
        $closes = time() + 5 * 24 * 3600;
        $s = $this->build_scenario([
            'allowsubmissionsfromdate' => $opens,
            'duedate'                  => $closes,
        ]);

        submission_ledger::upsert_for_cm_user_attempt($s['cmid'], (int) $s['student']->id, 0);

        $row = $DB->get_record('block_grade_me_sla_sub', [
            'cmid' => $s['cmid'], 'userid' => $s['student']->id, 'attemptnumber' => 0,
        ]);
        $this->assertSame(1, (int) $row->hasrule);
        $this->assertSame($opens, (int) $row->timeopens);
        $this->assertSame($closes, (int) $row->timecloses);
    }

    public function test_delete_for_cm_removes_rows_and_enqueues(): void {
        global $DB;
        $s = $this->build_scenario();
        submission_ledger::upsert_for_cm_user_attempt($s['cmid'], (int) $s['student']->id, 0);
        $DB->delete_records('block_grade_me_sla_queue'); // clear from prior step

        submission_ledger::delete_for_cm($s['cmid']);

        $this->assertSame(0, $DB->count_records('block_grade_me_sla_sub', ['cmid' => $s['cmid']]));
        $this->assertTrue($DB->record_exists('block_grade_me_sla_queue', [
            'courseid' => $s['course']->id,
            'groupid'  => $s['group']->id,
            'modtype'  => 'assign',
        ]));
    }

    public function test_delete_for_course_removes_everything(): void {
        global $DB;
        $s = $this->build_scenario();
        submission_ledger::upsert_for_cm_user_attempt($s['cmid'], (int) $s['student']->id, 0);

        submission_ledger::delete_for_course((int) $s['course']->id);

        $this->assertSame(0, $DB->count_records('block_grade_me_sla_sub',   ['courseid' => $s['course']->id]));
        $this->assertSame(0, $DB->count_records('block_grade_me_sla_group', ['courseid' => $s['course']->id]));
        $this->assertSame(0, $DB->count_records('block_grade_me_sla_trend', ['courseid' => $s['course']->id]));
        $this->assertSame(0, $DB->count_records('block_grade_me_sla_queue', ['courseid' => $s['course']->id]));
    }
}

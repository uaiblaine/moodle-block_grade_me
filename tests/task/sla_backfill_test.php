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
 * Tests for the sla_backfill scheduled task.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\task;

use advanced_testcase;
use block_grade_me\local\sla\group_resolver;

defined('MOODLE_INTERNAL') || die();

/**
 * @group block_grade_me
 * @covers \block_grade_me\task\sla_backfill
 */
class sla_backfill_test extends advanced_testcase {

    /**
     * Seed N submissions to walk.
     *
     * @param int $count
     * @return array{course: \stdClass, assign: object, students: array<int,\stdClass>, cmid: int}
     */
    private function seed_submissions(int $count): array {
        global $DB;

        $g = $this->getDataGenerator();
        $course = $g->create_course();
        $assign = $g->get_plugin_generator('mod_assign')->create_instance([
            'course'      => $course->id,
            'name'        => 'Backfill fixture',
            'grade'       => 100,
            'maxattempts' => -1,
        ]);
        $cmid = (int) $assign->cmid;
        $group = $g->create_group(['courseid' => $course->id]);

        $students = [];
        for ($i = 0; $i < $count; $i++) {
            $student = $g->create_user();
            $g->enrol_user($student->id, $course->id, 'student');
            $g->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);
            $DB->insert_record('assign_submission', (object) [
                'assignment'    => $assign->id,
                'userid'        => $student->id,
                'timecreated'   => time() - 7200,
                'timemodified'  => time() - 7200,
                'status'        => 'submitted',
                'attemptnumber' => 0,
                'latest'        => 1,
                'groupid'       => 0,
            ]);
            $students[] = $student;
        }

        return ['course' => $course, 'assign' => $assign, 'students' => $students, 'cmid' => $cmid];
    }

    protected function setUp(): void {
        parent::setUp();
        group_resolver::reset_cache();
    }

    public function test_no_op_when_inactive(): void {
        global $DB;
        $this->resetAfterTest();
        $this->seed_submissions(5);

        // Default state: sla_backfill_active is unset (== 0).
        $task = new sla_backfill();
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertSame(0, $DB->count_records('block_grade_me_sla_sub'));
    }

    public function test_processes_rows_when_active(): void {
        global $DB;
        $this->resetAfterTest();
        $this->seed_submissions(3);
        set_config('sla_backfill_active', 1, 'block_grade_me');

        $task = new sla_backfill();
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertSame(3, $DB->count_records('block_grade_me_sla_sub'));
    }

    public function test_cursor_advances_across_runs(): void {
        global $DB;
        $this->resetAfterTest();
        $this->seed_submissions(10);

        set_config('sla_backfill_active', 1, 'block_grade_me');
        set_config('sla_backfill_chunk', 4, 'block_grade_me');

        $task = new sla_backfill();
        ob_start();
        $task->execute();
        $cursor1 = (int) get_config('block_grade_me', 'sla_backfill_cursor');
        $task->execute();
        $cursor2 = (int) get_config('block_grade_me', 'sla_backfill_cursor');
        ob_end_clean();

        $this->assertGreaterThan(0, $cursor1);
        $this->assertGreaterThan($cursor1, $cursor2);
    }

    public function test_self_disables_when_complete(): void {
        global $DB;
        $this->resetAfterTest();
        $this->seed_submissions(3);

        set_config('sla_backfill_active', 1, 'block_grade_me');
        set_config('sla_backfill_chunk', 100, 'block_grade_me');

        $task = new sla_backfill();
        ob_start();
        $task->execute(); // Processes all 3.
        $task->execute(); // Past end of table; self-disables.
        ob_end_clean();

        $this->assertSame('0', (string) get_config('block_grade_me', 'sla_backfill_active'));
        $this->assertSame('0', (string) get_config('block_grade_me', 'sla_backfill_cursor'));
    }

    public function test_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $this->seed_submissions(3);

        set_config('sla_backfill_active', 1, 'block_grade_me');
        set_config('sla_backfill_chunk', 100, 'block_grade_me');

        $task = new sla_backfill();
        ob_start();
        $task->execute();
        // Reset and re-run; we should get the same row count.
        set_config('sla_backfill_active', 1, 'block_grade_me');
        set_config('sla_backfill_cursor', 0, 'block_grade_me');
        $task->execute();
        ob_end_clean();

        $this->assertSame(3, $DB->count_records('block_grade_me_sla_sub'));
    }
}

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
 * Tests for the sla_drain scheduled task.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\task;

use advanced_testcase;
use block_grade_me\local\sla\dirty_queue;

defined('MOODLE_INTERNAL') || die();

/**
 * @group block_grade_me
 * @covers \block_grade_me\task\sla_drain
 */
class sla_drain_test extends advanced_testcase {

    public function test_drains_queue_and_writes_rollups(): void {
        global $DB;
        $this->resetAfterTest();

        dirty_queue::enqueue(11, 22, 'assign');
        dirty_queue::enqueue(11, 23, 'assign');
        dirty_queue::enqueue(12, 24, 'assign');

        $task = new sla_drain();
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertSame(0, dirty_queue::size());
        $this->assertSame(3, $DB->count_records('block_grade_me_sla_group'));
    }

    public function test_drains_empty_queue_is_noop(): void {
        global $DB;
        $this->resetAfterTest();

        $task = new sla_drain();
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertSame(0, $DB->count_records('block_grade_me_sla_group'));
    }

    public function test_drain_is_idempotent_on_unchanged_queue(): void {
        global $DB;
        $this->resetAfterTest();

        dirty_queue::enqueue(1, 1, 'assign');
        $task = new sla_drain();
        ob_start();
        $task->execute();
        $task->execute();
        ob_end_clean();

        // Same rollup row, not two.
        $this->assertSame(1, $DB->count_records('block_grade_me_sla_group', [
            'courseid' => 1, 'groupid' => 1, 'modtype' => 'assign',
        ]));
    }
}

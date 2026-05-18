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
 * Tests for the dirty queue service.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\local\sla;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

/**
 * @group block_grade_me
 * @covers \block_grade_me\local\sla\dirty_queue
 */
class dirty_queue_test extends advanced_testcase {

    public function test_enqueue_adds_row(): void {
        global $DB;
        $this->resetAfterTest();
        dirty_queue::enqueue(10, 20, 'assign');
        $this->assertSame(1, $DB->count_records('block_grade_me_sla_queue'));
    }

    public function test_enqueue_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        dirty_queue::enqueue(10, 20, 'assign');
        dirty_queue::enqueue(10, 20, 'assign');
        dirty_queue::enqueue(10, 20, 'assign');
        $this->assertSame(1, $DB->count_records('block_grade_me_sla_queue'));
    }

    public function test_enqueue_distinct_tuples(): void {
        global $DB;
        $this->resetAfterTest();
        dirty_queue::enqueue(10, 20, 'assign');
        dirty_queue::enqueue(10, 21, 'assign');
        dirty_queue::enqueue(11, 20, 'assign');
        dirty_queue::enqueue(10, 20, 'quiz');
        $this->assertSame(4, $DB->count_records('block_grade_me_sla_queue'));
    }

    public function test_drain_empty_queue(): void {
        $this->resetAfterTest();
        $this->assertSame([], dirty_queue::drain(100));
    }

    public function test_drain_returns_and_deletes(): void {
        global $DB;
        $this->resetAfterTest();
        dirty_queue::enqueue(10, 20, 'assign');
        dirty_queue::enqueue(10, 21, 'assign');
        dirty_queue::enqueue(10, 22, 'assign');

        $rows = dirty_queue::drain(100);
        $this->assertCount(3, $rows);
        $this->assertSame(0, $DB->count_records('block_grade_me_sla_queue'));
    }

    public function test_drain_respects_batch_size(): void {
        global $DB;
        $this->resetAfterTest();
        for ($g = 1; $g <= 5; $g++) {
            dirty_queue::enqueue(10, $g, 'assign');
        }
        $rows = dirty_queue::drain(3);
        $this->assertCount(3, $rows);
        $this->assertSame(2, $DB->count_records('block_grade_me_sla_queue'));
    }

    public function test_drain_returns_fifo(): void {
        global $DB;
        $this->resetAfterTest();

        // Insert with explicit timeenqueued to force FIFO order rather than
        // relying on the test clock's granularity.
        $DB->insert_record('block_grade_me_sla_queue', (object) [
            'courseid' => 1, 'groupid' => 1, 'modtype' => 'assign', 'timeenqueued' => 1000,
        ]);
        $DB->insert_record('block_grade_me_sla_queue', (object) [
            'courseid' => 1, 'groupid' => 2, 'modtype' => 'assign', 'timeenqueued' => 2000,
        ]);
        $DB->insert_record('block_grade_me_sla_queue', (object) [
            'courseid' => 1, 'groupid' => 3, 'modtype' => 'assign', 'timeenqueued' => 3000,
        ]);

        $rows = dirty_queue::drain(2);
        $this->assertCount(2, $rows);
        $this->assertSame(1, (int) $rows[0]->groupid);
        $this->assertSame(2, (int) $rows[1]->groupid);
    }

    public function test_size_reflects_queue_depth(): void {
        $this->resetAfterTest();
        $this->assertSame(0, dirty_queue::size());
        dirty_queue::enqueue(1, 1, 'assign');
        dirty_queue::enqueue(1, 2, 'assign');
        $this->assertSame(2, dirty_queue::size());
        dirty_queue::drain(1);
        $this->assertSame(1, dirty_queue::size());
    }
}

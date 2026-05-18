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
 * Tests for the rollup recompute service.
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
 * @covers \block_grade_me\local\sla\rollup_service
 */
class rollup_service_test extends advanced_testcase {

    /**
     * Seed a ledger row with a known waiting time at the chosen "now".
     *
     * @param int $courseid
     * @param int $groupid
     * @param float $waitinghours
     * @param int $now reference timestamp
     * @param bool $graded if true, the row is graded (excluded from pending)
     */
    private function seed_pending_row(
        int $courseid,
        int $groupid,
        float $waitinghours,
        int $now,
        bool $graded = false
    ): void {
        global $DB;
        $timesubmitted = $now - (int) round($waitinghours * 3600);
        $DB->insert_record('block_grade_me_sla_sub', (object) [
            'courseid'         => $courseid,
            'groupid'          => $groupid,
            'cmid'             => 1,
            'modtype'          => 'assign',
            'iteminstance'     => 1,
            'userid'           => mt_rand(10000, 99999),
            'attemptnumber'    => 0,
            'submissionstatus' => 'submitted',
            'timesubmitted'    => $timesubmitted,
            'timegraded'       => $graded ? ($timesubmitted + 1800) : null,
            'waitinghours'     => $graded ? 0.5 : null,
            'hasrule'          => 0,
            'timeopens'        => null,
            'timecloses'       => null,
            'timecreated'      => $now,
            'timemodified'     => $now,
        ]);
    }

    public function test_empty_group_writes_zero_rollup(): void {
        global $DB;
        $this->resetAfterTest();

        $now = 1700000000;
        rollup_service::recompute_group(7, 3, 'assign', $now);

        $row = $DB->get_record('block_grade_me_sla_group', [
            'courseid' => 7, 'groupid' => 3, 'modtype' => 'assign',
        ]);
        $this->assertNotEmpty($row);
        $this->assertSame(0, (int) $row->pending);
        $this->assertSame(0, (int) $row->critical);
        $this->assertSame(0, (int) $row->overgoal);
        $this->assertNull($row->medianh);
        $this->assertNull($row->p90h);
        $this->assertNull($row->maxh);
        $this->assertSame($now, (int) $row->timerecomputed);
    }

    public function test_rollup_computes_stats_from_pending(): void {
        global $DB;
        $this->resetAfterTest();

        $now = 1700000000;
        // Waiting times: 1h, 10h, 30h, 60h, 200h
        $this->seed_pending_row(1, 1, 1.0, $now);
        $this->seed_pending_row(1, 1, 10.0, $now);
        $this->seed_pending_row(1, 1, 30.0, $now);
        $this->seed_pending_row(1, 1, 60.0, $now);
        $this->seed_pending_row(1, 1, 200.0, $now);

        rollup_service::recompute_group(1, 1, 'assign', $now);

        $row = $DB->get_record('block_grade_me_sla_group', [
            'courseid' => 1, 'groupid' => 1, 'modtype' => 'assign',
        ]);
        $this->assertSame(5, (int) $row->pending);
        $this->assertEqualsWithDelta(30.0, (float) $row->medianh, 0.05);
        $this->assertEqualsWithDelta(200.0, (float) $row->maxh, 0.05);
        // 5 values; rank 0.9 * 4 = 3.6 -> 60 + 0.6*(200-60) = 144.
        $this->assertEqualsWithDelta(144.0, (float) $row->p90h, 0.5);
    }

    public function test_rollup_counts_critical_and_overgoal(): void {
        global $DB;
        $this->resetAfterTest();
        $now = 1700000000;

        // Critical (>= 120) = 2, overgoal (> 24) = 3, pending = 4.
        $this->seed_pending_row(2, 5, 1.0, $now);    // not over goal
        $this->seed_pending_row(2, 5, 25.0, $now);   // over goal, not critical
        $this->seed_pending_row(2, 5, 150.0, $now);  // critical
        $this->seed_pending_row(2, 5, 300.0, $now);  // critical

        rollup_service::recompute_group(2, 5, 'assign', $now);

        $row = $DB->get_record('block_grade_me_sla_group', [
            'courseid' => 2, 'groupid' => 5, 'modtype' => 'assign',
        ]);
        $this->assertSame(4, (int) $row->pending);
        $this->assertSame(2, (int) $row->critical);
        $this->assertSame(3, (int) $row->overgoal);
    }

    public function test_graded_rows_are_excluded(): void {
        global $DB;
        $this->resetAfterTest();
        $now = 1700000000;

        $this->seed_pending_row(3, 2, 5.0, $now, true);  // graded — excluded
        $this->seed_pending_row(3, 2, 7.0, $now);

        rollup_service::recompute_group(3, 2, 'assign', $now);

        $row = $DB->get_record('block_grade_me_sla_group', [
            'courseid' => 3, 'groupid' => 2, 'modtype' => 'assign',
        ]);
        $this->assertSame(1, (int) $row->pending);
        $this->assertEqualsWithDelta(7.0, (float) $row->medianh, 0.05);
    }

    public function test_other_tuples_are_isolated(): void {
        global $DB;
        $this->resetAfterTest();
        $now = 1700000000;

        $this->seed_pending_row(1, 1, 5.0, $now);
        $this->seed_pending_row(1, 2, 100.0, $now);

        rollup_service::recompute_group(1, 1, 'assign', $now);

        $r1 = $DB->get_record('block_grade_me_sla_group', [
            'courseid' => 1, 'groupid' => 1, 'modtype' => 'assign',
        ]);
        $this->assertSame(1, (int) $r1->pending);
        $this->assertEqualsWithDelta(5.0, (float) $r1->medianh, 0.05);

        $r2 = $DB->get_record('block_grade_me_sla_group', [
            'courseid' => 1, 'groupid' => 2, 'modtype' => 'assign',
        ]);
        $this->assertFalse($r2);  // not computed yet
    }

    public function test_recompute_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $now = 1700000000;

        $this->seed_pending_row(4, 4, 12.0, $now);
        rollup_service::recompute_group(4, 4, 'assign', $now);
        rollup_service::recompute_group(4, 4, 'assign', $now);

        $count = $DB->count_records('block_grade_me_sla_group', [
            'courseid' => 4, 'groupid' => 4, 'modtype' => 'assign',
        ]);
        $this->assertSame(1, $count);
    }
}

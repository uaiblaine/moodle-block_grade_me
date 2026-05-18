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
 * Unit tests for the SLA stat helpers.
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
 * @covers \block_grade_me\local\sla\stats
 */
class stats_test extends advanced_testcase {

    public function test_median_empty(): void {
        $this->assertNull(stats::median([]));
    }

    public function test_median_singleton(): void {
        $this->assertSame(42.0, stats::median([42]));
    }

    public function test_median_odd_count(): void {
        $this->assertSame(5.0, stats::median([1, 5, 9]));
        $this->assertSame(5.0, stats::median([9, 1, 5]));
    }

    public function test_median_even_count(): void {
        $this->assertSame(3.0, stats::median([1, 2, 4, 5]));
        $this->assertSame(3.5, stats::median([1, 3, 4, 5]));
    }

    public function test_percentile_empty(): void {
        $this->assertNull(stats::percentile([], 90));
    }

    public function test_percentile_singleton(): void {
        $this->assertSame(7.0, stats::percentile([7], 90));
    }

    public function test_percentile_p0_and_p100(): void {
        $values = [1, 2, 3, 4, 5];
        $this->assertSame(1.0, stats::percentile($values, 0));
        $this->assertSame(5.0, stats::percentile($values, 100));
    }

    public function test_percentile_p50_matches_median(): void {
        $values = [1, 2, 4, 5];
        $this->assertEqualsWithDelta(stats::median($values), stats::percentile($values, 50), 1e-9);
    }

    public function test_percentile_p90_linear_interp(): void {
        // 10 values 1..10; rank = 0.9 * 9 = 8.1 -> 9 + 0.1*(10-9) = 9.1.
        $values = range(1, 10);
        $this->assertEqualsWithDelta(9.1, stats::percentile($values, 90), 1e-9);
    }

    public function test_percentile_clamps_out_of_range(): void {
        $values = [1, 2, 3, 4, 5];
        $this->assertSame(1.0, stats::percentile($values, -10));
        $this->assertSame(5.0, stats::percentile($values, 200));
    }

    public function test_max_value_empty(): void {
        $this->assertNull(stats::max_value([]));
    }

    public function test_max_value_basic(): void {
        $this->assertSame(7.0, stats::max_value([1, 7, 3]));
        $this->assertSame(0.0, stats::max_value([0, 0, 0]));
        $this->assertSame(-1.0, stats::max_value([-3, -1, -5]));
    }
}

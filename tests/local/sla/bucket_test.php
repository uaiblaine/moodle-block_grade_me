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
 * Unit tests for the SLA bucket classifier.
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
 * @covers \block_grade_me\local\sla\bucket
 */
class bucket_test extends advanced_testcase {

    public function test_bucket_for_default_thresholds(): void {
        $this->assertSame(bucket::EXCELLENT, bucket::bucket_for(0.0));
        $this->assertSame(bucket::EXCELLENT, bucket::bucket_for(23.99));
        $this->assertSame(bucket::GOOD, bucket::bucket_for(24.0));
        $this->assertSame(bucket::GOOD, bucket::bucket_for(47.99));
        $this->assertSame(bucket::REGULAR, bucket::bucket_for(48.0));
        $this->assertSame(bucket::REGULAR, bucket::bucket_for(119.99));
        $this->assertSame(bucket::CRITICAL, bucket::bucket_for(120.0));
        $this->assertSame(bucket::CRITICAL, bucket::bucket_for(999.0));
    }

    public function test_bucket_for_null_is_excellent(): void {
        // Null means "no pending work" — that's a clean / "all clear" state,
        // not a critical one.
        $this->assertSame(bucket::EXCELLENT, bucket::bucket_for(null));
    }

    public function test_bucket_for_custom_thresholds(): void {
        $custom = [12, 24, 48];
        $this->assertSame(bucket::EXCELLENT, bucket::bucket_for(11.5, $custom));
        $this->assertSame(bucket::GOOD, bucket::bucket_for(20.0, $custom));
        $this->assertSame(bucket::REGULAR, bucket::bucket_for(36.0, $custom));
        $this->assertSame(bucket::CRITICAL, bucket::bucket_for(48.0, $custom));
    }

    public function test_parse_thresholds_valid(): void {
        $this->assertSame([24, 48, 120], bucket::parse_thresholds('24,48,120'));
        $this->assertSame([10, 20, 30], bucket::parse_thresholds(' 10 , 20 , 30 '));
    }

    public function test_parse_thresholds_invalid_falls_back(): void {
        $default = bucket::default_thresholds();
        $this->assertSame($default, bucket::parse_thresholds(null));
        $this->assertSame($default, bucket::parse_thresholds(''));
        $this->assertSame($default, bucket::parse_thresholds('1,2'));
        $this->assertSame($default, bucket::parse_thresholds('1,2,3,4'));
        $this->assertSame($default, bucket::parse_thresholds('a,b,c'));
        $this->assertSame($default, bucket::parse_thresholds('0,1,2'));
        $this->assertSame($default, bucket::parse_thresholds('-1,2,3'));
        $this->assertSame($default, bucket::parse_thresholds('48,24,120'));
        $this->assertSame($default, bucket::parse_thresholds('24,48,48'));
    }

    public function test_bucket_color(): void {
        $this->assertSame('#10b981', bucket::bucket_color(bucket::EXCELLENT));
        $this->assertSame('#f59e0b', bucket::bucket_color(bucket::GOOD));
        $this->assertSame('#f97316', bucket::bucket_color(bucket::REGULAR));
        $this->assertSame('#ef4444', bucket::bucket_color(bucket::CRITICAL));
        $this->assertSame('#a8a29e', bucket::bucket_color('unknown-bucket'));
    }

    public function test_bucket_label_key(): void {
        $this->assertSame('bucket_excellent', bucket::bucket_label_key(bucket::EXCELLENT));
        $this->assertSame('bucket_good', bucket::bucket_label_key(bucket::GOOD));
        $this->assertSame('bucket_regular', bucket::bucket_label_key(bucket::REGULAR));
        $this->assertSame('bucket_critical', bucket::bucket_label_key(bucket::CRITICAL));
    }
}

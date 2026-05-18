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
 * Integration tests for the open/close rule resolver.
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
 * @covers \block_grade_me\local\sla\rule_resolver
 */
class rule_resolver_test extends advanced_testcase {

    public function test_no_dates_no_rule(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        $result = rule_resolver::resolve_rule((int) $assign->cmid, (int) $user->id, 0);

        $this->assertFalse($result['has_rule']);
        $this->assertNull($result['opens_at']);
        $this->assertNull($result['closes_at']);
        $this->assertSame(rule_resolver::URGENCY_NONE, $result['urgency']);
    }

    public function test_dates_only(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $opens = time() - 3600;
        $closes = time() + 5 * 24 * 3600;
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course'                   => $course->id,
            'allowsubmissionsfromdate' => $opens,
            'duedate'                  => $closes,
        ]);
        $user = $this->getDataGenerator()->create_user();

        $result = rule_resolver::resolve_rule((int) $assign->cmid, (int) $user->id, 0);

        $this->assertTrue($result['has_rule']);
        $this->assertSame($opens, $result['opens_at']);
        $this->assertSame($closes, $result['closes_at']);
        $this->assertSame(rule_resolver::URGENCY_ONTRACK, $result['urgency']);
    }

    public function test_group_override_replaces_duedate(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $opens = time() - 3600;
        $closes = time() + 5 * 24 * 3600;
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course'                   => $course->id,
            'allowsubmissionsfromdate' => $opens,
            'duedate'                  => $closes,
        ]);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        $overrideclose = time() + 30 * 24 * 3600;
        $DB->insert_record('assign_overrides', (object) [
            'assignid'                 => $assign->id,
            'groupid'                  => $group->id,
            'userid'                   => null,
            'sortorder'                => 1,
            'allowsubmissionsfromdate' => $opens,
            'duedate'                  => $overrideclose,
        ]);

        $result = rule_resolver::resolve_rule((int) $assign->cmid, (int) $user->id, (int) $group->id);

        $this->assertTrue($result['has_rule']);
        $this->assertSame($overrideclose, $result['closes_at']);
    }

    public function test_user_override_removes_rule_from_group_view(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $opens = time() - 3600;
        $closes = time() + 5 * 24 * 3600;
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course'                   => $course->id,
            'allowsubmissionsfromdate' => $opens,
            'duedate'                  => $closes,
        ]);
        $user = $this->getDataGenerator()->create_user();

        $DB->insert_record('assign_overrides', (object) [
            'assignid'  => $assign->id,
            'groupid'   => null,
            'userid'    => $user->id,
            'sortorder' => 1,
            'duedate'   => $closes + 3600,
        ]);

        $result = rule_resolver::resolve_rule((int) $assign->cmid, (int) $user->id, 0);

        $this->assertFalse($result['has_rule']);
    }

    public function test_unknown_cm_returns_no_rule(): void {
        $this->resetAfterTest();
        $result = rule_resolver::resolve_rule(999999, 1, 0);
        $this->assertFalse($result['has_rule']);
    }

    public function test_urgency_buckets(): void {
        $now = 1700000000;
        $this->assertSame(rule_resolver::URGENCY_NONE,    rule_resolver::urgency_for(null,                 $now));
        $this->assertSame(rule_resolver::URGENCY_OVERDUE, rule_resolver::urgency_for($now - 1,             $now));
        $this->assertSame(rule_resolver::URGENCY_URGENT,  rule_resolver::urgency_for($now + 3600,          $now));
        $this->assertSame(rule_resolver::URGENCY_URGENT,  rule_resolver::urgency_for($now + 23 * 3600,     $now));
        $this->assertSame(rule_resolver::URGENCY_SOON,    rule_resolver::urgency_for($now + 25 * 3600,     $now));
        $this->assertSame(rule_resolver::URGENCY_SOON,    rule_resolver::urgency_for($now + 71 * 3600,     $now));
        $this->assertSame(rule_resolver::URGENCY_ONTRACK, rule_resolver::urgency_for($now + 73 * 3600,     $now));
    }
}

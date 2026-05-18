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
 * Integration tests for the latest-joined group resolver.
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
 * @covers \block_grade_me\local\sla\group_resolver
 */
class group_resolver_test extends advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        group_resolver::reset_cache();
    }

    public function test_no_groups_returns_zero(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $this->assertSame(0, group_resolver::resolve_group_for_user((int) $course->id, (int) $user->id));
    }

    public function test_single_group(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_group_member([
            'groupid' => $group->id,
            'userid'  => $user->id,
        ]);

        $this->assertSame((int) $group->id, group_resolver::resolve_group_for_user(
            (int) $course->id,
            (int) $user->id
        ));
    }

    public function test_multi_group_picks_latest_joined(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $g1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $g2 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        // Insert memberships directly so we can control timeadded explicitly —
        // the test-runner clock has second granularity which makes the
        // tiebreaker brittle when the generator is used.
        $now = time();
        $DB->insert_record('groups_members', (object) [
            'groupid'   => $g1->id,
            'userid'    => $user->id,
            'timeadded' => $now - 1000,
            'component' => '',
            'itemid'    => 0,
        ]);
        $DB->insert_record('groups_members', (object) [
            'groupid'   => $g2->id,
            'userid'    => $user->id,
            'timeadded' => $now,
            'component' => '',
            'itemid'    => 0,
        ]);

        $this->assertSame((int) $g2->id, group_resolver::resolve_group_for_user(
            (int) $course->id,
            (int) $user->id
        ));
    }

    public function test_other_courses_ignored(): void {
        $this->resetAfterTest();
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user->id, $course2->id);
        $g1 = $this->getDataGenerator()->create_group(['courseid' => $course1->id]);
        $this->getDataGenerator()->create_group_member([
            'groupid' => $g1->id,
            'userid'  => $user->id,
        ]);

        $this->assertSame((int) $g1->id, group_resolver::resolve_group_for_user(
            (int) $course1->id,
            (int) $user->id
        ));
        $this->assertSame(0, group_resolver::resolve_group_for_user(
            (int) $course2->id,
            (int) $user->id
        ));
    }

    public function test_request_cache_returns_same_value(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_group_member([
            'groupid' => $group->id,
            'userid'  => $user->id,
        ]);

        $first = group_resolver::resolve_group_for_user((int) $course->id, (int) $user->id);
        $second = group_resolver::resolve_group_for_user((int) $course->id, (int) $user->id);
        $this->assertSame($first, $second);
    }
}

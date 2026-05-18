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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Latest-joined-group resolver.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\local\sla;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolves which group a user's submission contributes to.
 *
 * A user can belong to multiple groups in the same course; the SLA rollup
 * attributes each submission to the user's most-recently-joined group
 * (ORDER BY timeadded DESC, then groupid DESC for a stable tiebreaker when
 * memberships share a timestamp). This mirrors the reference SQL in the brief
 * and keeps rollup tables compact.
 */
class group_resolver {
    /** @var array<string, int> Per-request memo keyed by "courseid:userid". */
    private static $cache = [];

    /**
     * Latest-joined groupid for a user in a course, or 0 if the user is not in any group.
     *
     * @param int $courseid
     * @param int $userid
     * @return int
     */
    public static function resolve_group_for_user(int $courseid, int $userid): int {
        $key = $courseid . ':' . $userid;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        global $DB;
        $sql = "SELECT gm.groupid
                  FROM {groups_members} gm
                  JOIN {groups} g ON g.id = gm.groupid
                 WHERE g.courseid = :courseid AND gm.userid = :userid
              ORDER BY gm.timeadded DESC, gm.groupid DESC";
        $row = $DB->get_record_sql(
            $sql,
            ['courseid' => $courseid, 'userid' => $userid],
            IGNORE_MULTIPLE
        );
        $groupid = $row ? (int) $row->groupid : 0;
        self::$cache[$key] = $groupid;
        return $groupid;
    }

    /**
     * Reset the per-request memo. Used by tests and any CLI script that mutates
     * group membership mid-process.
     */
    public static function reset_cache(): void {
        self::$cache = [];
    }
}

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
 * Open/close rule resolver for an assignment row.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\local\sla;

defined('MOODLE_INTERNAL') || die();

/**
 * Computes the effective (opens_at, closes_at) for an assignment for one user
 * inside one group, honouring group + user overrides.
 *
 * - User override → the rule is hidden from the group view (the brief: per-student
 *   overrides don't fit "group rule.").
 * - Group override → replaces the assign defaults.
 * - No dates → has_rule is false.
 */
class rule_resolver {
    public const URGENCY_OVERDUE = 'overdue';
    public const URGENCY_URGENT  = 'urgent';
    public const URGENCY_SOON    = 'soon';
    public const URGENCY_ONTRACK = 'on-track';
    public const URGENCY_NONE    = 'none';

    /**
     * Effective rule for a (cmid, userid, groupid) tuple.
     *
     * A rule has up to three dates:
     *  - opens_at  ({assign}.allowsubmissionsfromdate)  — when the activity opens.
     *  - closes_at ({assign}.duedate)                   — soft due date.
     *  - cutoff_at ({assign}.cutoffdate)                — hard deadline.
     *
     * `has_rule` is true when at least one of the three is set. Group overrides
     * (in {assign_overrides}) can rewrite any of the three for the group.
     *
     * @param int $cmid
     * @param int $userid
     * @param int $groupid 0 if not in a group
     * @return array{has_rule:bool,opens_at:?int,closes_at:?int,cutoff_at:?int,urgency:string}
     */
    public static function resolve_rule(int $cmid, int $userid, int $groupid): array {
        global $DB;

        $sql = "SELECT a.id AS assignid,
                       a.allowsubmissionsfromdate,
                       a.duedate,
                       a.cutoffdate
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {assign} a ON a.id = cm.instance
                 WHERE cm.id = :cmid AND m.name = 'assign'";
        $assign = $DB->get_record_sql($sql, ['cmid' => $cmid]);
        if (!$assign) {
            return self::no_rule();
        }

        $opens  = !empty($assign->allowsubmissionsfromdate) ? (int) $assign->allowsubmissionsfromdate : null;
        $closes = !empty($assign->duedate) ? (int) $assign->duedate : null;
        $cutoff = !empty($assign->cutoffdate) ? (int) $assign->cutoffdate : null;

        // A per-user override hides the rule from the group view.
        if ($DB->record_exists('assign_overrides', [
            'assignid' => $assign->assignid,
            'userid'   => $userid,
        ])) {
            return self::no_rule();
        }

        // Group override replaces the assign defaults when present.
        if ($groupid > 0) {
            $groupoverride = $DB->get_record('assign_overrides', [
                'assignid' => $assign->assignid,
                'groupid'  => $groupid,
            ]);
            if ($groupoverride) {
                if (property_exists($groupoverride, 'allowsubmissionsfromdate')) {
                    $opens = !empty($groupoverride->allowsubmissionsfromdate)
                        ? (int) $groupoverride->allowsubmissionsfromdate
                        : null;
                }
                if (property_exists($groupoverride, 'duedate')) {
                    $closes = !empty($groupoverride->duedate)
                        ? (int) $groupoverride->duedate
                        : null;
                }
                if (property_exists($groupoverride, 'cutoffdate')) {
                    $cutoff = !empty($groupoverride->cutoffdate)
                        ? (int) $groupoverride->cutoffdate
                        : null;
                }
            }
        }

        $hasrule = ($opens !== null) || ($closes !== null) || ($cutoff !== null);

        // Urgency is keyed off the closing date if present; otherwise off the
        // cutoff so activities with only a hard deadline still bucket correctly.
        $urgencyref = $closes ?? $cutoff;

        return [
            'has_rule'  => $hasrule,
            'opens_at'  => $opens,
            'closes_at' => $closes,
            'cutoff_at' => $cutoff,
            'urgency'   => self::urgency_for($urgencyref, time()),
        ];
    }

    /**
     * Map (closes_at - now) to an urgency bucket.
     *
     * @param int|null $closesat
     * @param int $now
     * @return string
     */
    public static function urgency_for(?int $closesat, int $now): string {
        if ($closesat === null) {
            return self::URGENCY_NONE;
        }
        $secondsleft = $closesat - $now;
        if ($secondsleft < 0) {
            return self::URGENCY_OVERDUE;
        }
        if ($secondsleft < 24 * 3600) {
            return self::URGENCY_URGENT;
        }
        if ($secondsleft < 72 * 3600) {
            return self::URGENCY_SOON;
        }
        return self::URGENCY_ONTRACK;
    }

    /**
     * Sentinel "no rule" return value.
     *
     * @return array{has_rule:bool,opens_at:?int,closes_at:?int,urgency:string}
     */
    private static function no_rule(): array {
        return [
            'has_rule'  => false,
            'opens_at'  => null,
            'closes_at' => null,
            'cutoff_at' => null,
            'urgency'   => self::URGENCY_NONE,
        ];
    }
}

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
 * Rollup recompute service.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\local\sla;

defined('MOODLE_INTERNAL') || die();

/**
 * Recomputes the {block_grade_me_sla_group} row for one (courseid, groupid, modtype)
 * tuple from the per-submission ledger. Called by the drain task.
 *
 * Pending = submissions with status='submitted' and timegraded IS NULL.
 * Median / p90 / max are computed in PHP from now - timesubmitted so the
 * "waiting" duration is current at recompute time (not at upsert time).
 */
class rollup_service {

    /**
     * Recompute and upsert the rollup row for one tuple.
     *
     * @param int $courseid
     * @param int $groupid
     * @param string $modtype
     * @param int|null $now Override "now" for testing; defaults to time().
     */
    public static function recompute_group(
        int $courseid,
        int $groupid,
        string $modtype,
        ?int $now = null
    ): void {
        global $DB;

        $now = $now ?? time();

        $includehiddenactivities = bucket::include_hidden_activities();
        $sql = "SELECT sub.id, sub.timesubmitted
                  FROM {block_grade_me_sla_sub} sub
                  JOIN {course_modules} cm ON cm.id = sub.cmid
                 WHERE sub.courseid = :courseid
                   AND sub.groupid = :groupid
                   AND sub.modtype = :modtype
                   AND sub.submissionstatus = 'submitted'
                   AND sub.timegraded IS NULL
                   AND sub.timesubmitted > 0";
        if (!$includehiddenactivities) {
            $sql .= " AND cm.visible = 1";
        }
        $params = [
            'courseid' => $courseid,
            'groupid'  => $groupid,
            'modtype'  => $modtype,
        ];
        $maxagecutoff = bucket::maxage_cutoff();
        if ($maxagecutoff !== null) {
            $sql .= " AND sub.timesubmitted >= :maxagecutoff";
            $params['maxagecutoff'] = $maxagecutoff;
        }
        $rows = $DB->get_records_sql($sql, $params);

        $thresholds = bucket::parse_thresholds(get_config('block_grade_me', 'sla_thresholds') ?: null);
        $goal = (int) (get_config('block_grade_me', 'sla_goal') ?: 24);
        $criticalmin = $thresholds[2];

        $waitings = [];
        $pending = 0;
        $critical = 0;
        $overgoal = 0;

        foreach ($rows as $r) {
            $waitinghours = max(0.0, ($now - (int) $r->timesubmitted) / 3600.0);
            $waitings[] = $waitinghours;
            $pending++;
            if ($waitinghours >= $criticalmin) {
                $critical++;
            }
            if ($waitinghours > $goal) {
                $overgoal++;
            }
        }

        $medianh = stats::median($waitings);
        $p90h    = stats::percentile($waitings, 90.0);
        $maxh    = stats::max_value($waitings);

        $existing = $DB->get_record(
            'block_grade_me_sla_group',
            ['courseid' => $courseid, 'groupid' => $groupid, 'modtype' => $modtype],
            'id'
        );

        $record = (object) [
            'courseid'       => $courseid,
            'groupid'        => $groupid,
            'modtype'        => $modtype,
            'pending'        => $pending,
            'critical'       => $critical,
            'overgoal'       => $overgoal,
            'medianh'        => $medianh,
            'p90h'           => $p90h,
            'maxh'           => $maxh,
            'timerecomputed' => $now,
            'timemodified'   => $now,
        ];

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('block_grade_me_sla_group', $record);
        } else {
            $DB->insert_record('block_grade_me_sla_group', $record);
        }
    }
}

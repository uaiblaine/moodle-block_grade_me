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
 * Daily-trend recompute service.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\local\sla;

defined('MOODLE_INTERNAL') || die();

/**
 * Recomputes one row of {block_grade_me_sla_trend}: the median waiting time
 * for items GRADED on a given calendar day, per (courseid, groupid, modtype).
 *
 * The sparkline in the block reads up to 30 consecutive day rows; missing days
 * are rendered as gaps. Older rows are pruned by {@see self::prune_older_than()}
 * to keep the table bounded.
 */
class trend_service {

    /**
     * Recompute and upsert the trend row for one tuple-on-day.
     *
     * @param int $courseid
     * @param int $groupid
     * @param string $modtype
     * @param int $day YYYYMMDD
     */
    public static function recompute_day(int $courseid, int $groupid, string $modtype, int $day): void {
        global $DB;

        $start = self::day_start_ts($day);
        $end = $start + 86400;

        $sql = "SELECT waitinghours
                  FROM {block_grade_me_sla_sub}
                 WHERE courseid = :courseid
                   AND groupid = :groupid
                   AND modtype = :modtype
                   AND timegraded >= :start
                   AND timegraded <  :end
                   AND waitinghours IS NOT NULL";
        $params = [
            'courseid' => $courseid,
            'groupid'  => $groupid,
            'modtype'  => $modtype,
            'start'    => $start,
            'end'      => $end,
        ];

        $vals = [];
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $r) {
            $vals[] = (float) $r->waitinghours;
        }
        $rs->close();

        $median = stats::median($vals);
        $now = time();

        $existing = $DB->get_record(
            'block_grade_me_sla_trend',
            ['courseid' => $courseid, 'groupid' => $groupid, 'modtype' => $modtype, 'day' => $day],
            'id'
        );
        $record = (object) [
            'courseid'     => $courseid,
            'groupid'      => $groupid,
            'modtype'      => $modtype,
            'day'          => $day,
            'medianh'      => $median,
            'numgraded'    => count($vals),
            'timemodified' => $now,
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('block_grade_me_sla_trend', $record);
        } else {
            $DB->insert_record('block_grade_me_sla_trend', $record);
        }
    }

    /**
     * Drop trend rows older than $days days ago. Called by the daily task to
     * keep the table from growing forever.
     *
     * @param int $days
     */
    public static function prune_older_than(int $days): void {
        global $DB;
        $cutoff = (int) date('Ymd', time() - $days * 86400);
        $DB->delete_records_select('block_grade_me_sla_trend', 'day < ?', [$cutoff]);
    }

    /**
     * Convert a YYYYMMDD integer to a unix timestamp for the start of that day
     * in the server's timezone.
     *
     * @param int $yyyymmdd
     * @return int
     */
    public static function day_start_ts(int $yyyymmdd): int {
        $y = (int) intdiv($yyyymmdd, 10000);
        $m = (int) intdiv($yyyymmdd % 10000, 100);
        $d = (int) ($yyyymmdd % 100);
        return mktime(0, 0, 0, $m, $d, $y);
    }

    /**
     * Day key (YYYYMMDD) for a unix timestamp.
     *
     * @param int $ts
     * @return int
     */
    public static function day_key(int $ts): int {
        return (int) date('Ymd', $ts);
    }
}

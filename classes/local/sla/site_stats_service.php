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
 * Site-wide SLA stats service.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\local\sla;

defined('MOODLE_INTERNAL') || die();

/**
 * Recomputes one row of {block_grade_me_sla_site}: the school-wide median
 * waiting time and the 10th-percentile (top 10% fastest teachers) for items
 * graded on a given day, per modtype. Used by the "School / Top 10%" compare
 * row in the group card.
 */
class site_stats_service {

    /**
     * Recompute and upsert the site row for one (modtype, day).
     *
     * @param string $modtype
     * @param int $day YYYYMMDD
     */
    public static function recompute_day(string $modtype, int $day): void {
        global $DB;

        $start = trend_service::day_start_ts($day);
        $end = $start + 86400;

        $sql = "SELECT waitinghours
                  FROM {block_grade_me_sla_sub}
                 WHERE modtype = :modtype
                   AND timegraded >= :start
                   AND timegraded <  :end
                   AND waitinghours IS NOT NULL";
        $params = ['modtype' => $modtype, 'start' => $start, 'end' => $end];

        $vals = [];
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $r) {
            $vals[] = (float) $r->waitinghours;
        }
        $rs->close();

        $median = stats::median($vals);
        // Top 10% fastest = the 10th percentile (waiting time at the fast end).
        $p10 = stats::percentile($vals, 10.0);
        $now = time();

        $existing = $DB->get_record(
            'block_grade_me_sla_site',
            ['modtype' => $modtype, 'day' => $day],
            'id'
        );
        $record = (object) [
            'modtype'      => $modtype,
            'day'          => $day,
            'medianh'      => $median,
            'p10h'         => $p10,
            'numgraded'    => count($vals),
            'timemodified' => $now,
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('block_grade_me_sla_site', $record);
        } else {
            $DB->insert_record('block_grade_me_sla_site', $record);
        }
    }

    /**
     * Read the latest cached row for a modtype (any day) for display in the
     * compare row of a group card. Returns null when no rows exist.
     *
     * @param string $modtype
     * @return \stdClass|null { medianh, p10h, day }
     */
    public static function latest(string $modtype): ?\stdClass {
        global $DB;
        $rows = $DB->get_records(
            'block_grade_me_sla_site',
            ['modtype' => $modtype],
            'day DESC',
            'medianh, p10h, day',
            0,
            1
        );
        if (empty($rows)) {
            return null;
        }
        return reset($rows);
    }
}

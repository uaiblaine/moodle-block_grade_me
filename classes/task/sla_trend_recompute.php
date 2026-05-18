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
 * Daily SLA trend recompute task.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\task;

use block_grade_me\local\sla\trend_service;

defined('MOODLE_INTERNAL') || die();

/**
 * Walks every (courseid, groupid, modtype) tuple that has ledger activity and
 * recomputes yesterday's trend row. Then prunes anything older than 60 days
 * so the table stays bounded.
 */
class sla_trend_recompute extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_sla_trend_recompute', 'block_grade_me');
    }

    public function execute() {
        global $DB;

        // Use server-local "yesterday" so the row aligns with the calendar day
        // the sparkline labels in the UI.
        $yesterday = trend_service::day_key(time() - 86400);

        $rs = $DB->get_recordset_sql(
            "SELECT DISTINCT courseid, groupid, modtype FROM {block_grade_me_sla_sub}"
        );
        $processed = 0;
        foreach ($rs as $row) {
            trend_service::recompute_day(
                (int) $row->courseid,
                (int) $row->groupid,
                (string) $row->modtype,
                $yesterday
            );
            $processed++;
        }
        $rs->close();

        trend_service::prune_older_than(60);

        mtrace("sla_trend_recompute: $processed (course,group,modtype) tuples updated for day $yesterday");
    }
}

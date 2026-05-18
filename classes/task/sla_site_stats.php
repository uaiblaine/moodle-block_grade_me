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
 * Daily site-wide SLA stats task.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\task;

use block_grade_me\local\sla\site_stats_service;
use block_grade_me\local\sla\trend_service;

defined('MOODLE_INTERNAL') || die();

/**
 * Recomputes the site-wide row for yesterday (per modtype) for the
 * "School median" / "Top 10%" comparison.
 */
class sla_site_stats extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_sla_site_stats', 'block_grade_me');
    }

    public function execute() {
        $yesterday = trend_service::day_key(time() - 86400);
        site_stats_service::recompute_day('assign', $yesterday);
        mtrace("sla_site_stats: recomputed site row for assign on day $yesterday");
    }
}

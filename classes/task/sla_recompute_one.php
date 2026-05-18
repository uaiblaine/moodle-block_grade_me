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
 * Adhoc task — recompute one rollup tuple. Queued right after a
 * submission_graded event so the block reflects the change on the next
 * cron tick rather than waiting up to five minutes for sla_drain.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\task;

use block_grade_me\local\sla\rollup_service;

defined('MOODLE_INTERNAL') || die();

/**
 * Recompute one (courseid, groupid, modtype) rollup. Customdata must carry
 * those three keys. Also purges the responsiveness_payload cache so the next
 * WS render sees the fresh numbers.
 */
class sla_recompute_one extends \core\task\adhoc_task {

    public function get_name(): string {
        return get_string('task_sla_recompute_one', 'block_grade_me');
    }

    public function execute() {
        $data = $this->get_custom_data();
        if (empty($data->courseid) || empty($data->modtype)) {
            return;
        }
        rollup_service::recompute_group(
            (int) $data->courseid,
            (int) ($data->groupid ?? 0),
            (string) $data->modtype
        );
        // The WS payload is cached per (userid, courseid) for 15 min. Purge so
        // the next render returns the freshly-computed numbers.
        \cache_helper::purge_by_definition('block_grade_me', 'responsiveness_payload');
    }
}

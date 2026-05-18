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
 * SLA dirty-queue drain task.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\task;

use block_grade_me\local\sla\dirty_queue;
use block_grade_me\local\sla\rollup_service;

defined('MOODLE_INTERNAL') || die();

/**
 * Drains the SLA dirty queue, calling {@see rollup_service::recompute_group()}
 * for each tuple. Cooperative time cap so a backed-up queue doesn't run cron
 * past its deadline.
 */
class sla_drain extends \core\task\scheduled_task {

    /** @var int Wall-clock seconds before yielding back to cron. */
    public const SOFT_TIME_CAP_SECONDS = 50;

    /**
     * Task name shown in the admin UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_sla_drain', 'block_grade_me');
    }

    /**
     * Run the drain.
     */
    public function execute() {
        $batchsize = (int) (get_config('block_grade_me', 'sla_drain_batch') ?: 200);
        $start = microtime(true);
        $processed = 0;

        do {
            $tuples = dirty_queue::drain($batchsize);
            if (empty($tuples)) {
                break;
            }
            foreach ($tuples as $tuple) {
                if (microtime(true) - $start >= self::SOFT_TIME_CAP_SECONDS) {
                    mtrace("sla_drain: soft time cap reached after $processed tuples; leaving rest for next cron");
                    return;
                }
                rollup_service::recompute_group(
                    (int) $tuple->courseid,
                    (int) $tuple->groupid,
                    (string) $tuple->modtype
                );
                $processed++;
            }
        } while (true);

        mtrace("sla_drain: queue empty after $processed tuples");
    }
}

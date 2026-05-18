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
 * SLA backfill task — chunked walk of {assign_submission}.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\task;

use block_grade_me\local\sla\bucket;
use block_grade_me\local\sla\submission_ledger;

defined('MOODLE_INTERNAL') || die();

/**
 * Walks {assign_submission} in chunks ordered by id, persists a cursor in
 * config, and rebuilds the ledger from scratch. Idempotent: the per-row
 * upsert is keyed by (cmid, userid, attemptnumber).
 *
 * Stays a no-op until the reset action (CLI or admin UI) sets the
 * `sla_backfill_active` config flag to 1. Self-disables when it reaches the
 * end of the table so it doesn't burn cron once the rebuild is done.
 */
class sla_backfill extends \core\task\scheduled_task {

    /** @var int Wall-clock seconds before yielding back to cron. */
    public const SOFT_TIME_CAP_SECONDS = 50;

    public function get_name(): string {
        return get_string('task_sla_backfill', 'block_grade_me');
    }

    public function execute() {
        $active = (int) get_config('block_grade_me', 'sla_backfill_active');
        if ($active !== 1) {
            // No-op when not in a rebuild cycle. Log it so admins can tell
            // from the cron output that the task is wired up and skipped
            // intentionally rather than silently failing.
            mtrace('sla_backfill: skipped (sla_backfill_active=0; run Reset to activate)');
            return;
        }

        global $DB;
        $chunksize = (int) (get_config('block_grade_me', 'sla_backfill_chunk') ?: 5000);
        $cursor = (int) (get_config('block_grade_me', 'sla_backfill_cursor') ?: 0);

        // Skip submissions from hidden courses at the SQL level — no point
        // streaming them only to have submission_ledger::upsert reject them.
        // include_hidden_courses() = false (default) → only visible courses.
        $coursefilter = '';
        if (!bucket::include_hidden_courses()) {
            $coursefilter = ' AND c.visible = 1 ';
        }
        $sql = "SELECT sub.id, sub.userid, sub.attemptnumber, sub.status, cm.id AS cmid
                  FROM {assign_submission} sub
                  JOIN {assign} a ON a.id = sub.assignment
                  JOIN {course_modules} cm ON cm.instance = sub.assignment
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                  JOIN {course} c ON c.id = a.course
                 WHERE sub.id > :cursor
                   $coursefilter
              ORDER BY sub.id ASC";

        $start = microtime(true);
        $rs = $DB->get_recordset_sql($sql, ['cursor' => $cursor], 0, $chunksize);
        $processed = 0;
        $maxid = $cursor;

        foreach ($rs as $row) {
            if (microtime(true) - $start >= self::SOFT_TIME_CAP_SECONDS) {
                break;
            }
            submission_ledger::upsert_for_cm_user_attempt(
                (int) $row->cmid,
                (int) $row->userid,
                (int) $row->attemptnumber
            );
            $processed++;
            $maxid = (int) $row->id;
        }
        $rs->close();

        if ($processed === 0) {
            // We're past the end of the table; disable the backfill until the
            // next reset.
            set_config('sla_backfill_cursor', 0, 'block_grade_me');
            set_config('sla_backfill_active', 0, 'block_grade_me');
            mtrace('sla_backfill: complete; cursor reset and task deactivated');
            return;
        }

        set_config('sla_backfill_cursor', $maxid, 'block_grade_me');
        mtrace("sla_backfill: processed $processed rows; cursor advanced to id $maxid");
    }
}

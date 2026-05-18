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
 * Dirty-queue service.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\local\sla;

defined('MOODLE_INTERNAL') || die();

/**
 * Insert-or-ignore queue of (courseid, groupid, modtype) tuples waiting for the
 * drain task to recompute their rollup.
 *
 * Producer side: observers + the backfill task call {@see self::enqueue()}.
 * Consumer side: the sla_drain scheduled task calls {@see self::drain()} which
 * pops up to N rows in a single transaction so concurrent producers can't see
 * the same tuple twice.
 */
class dirty_queue {

    /**
     * Add a tuple to the queue. Idempotent: a row already present for the same
     * tuple is left untouched.
     *
     * @param int $courseid
     * @param int $groupid 0 = course-wide bucket
     * @param string $modtype
     */
    public static function enqueue(int $courseid, int $groupid, string $modtype): void {
        global $DB;
        $key = [
            'courseid' => $courseid,
            'groupid'  => $groupid,
            'modtype'  => $modtype,
        ];
        if ($DB->record_exists('block_grade_me_sla_queue', $key)) {
            return;
        }
        $record = (object) $key;
        $record->timeenqueued = time();
        $DB->insert_record('block_grade_me_sla_queue', $record, false);
    }

    /**
     * Pop up to $batchsize rows in FIFO order and return them as plain stdClass.
     * The rows are removed from the queue inside a transaction so a concurrent
     * call cannot return overlapping work.
     *
     * @param int $batchsize
     * @return \stdClass[] Each row has courseid, groupid, modtype, timeenqueued.
     */
    public static function drain(int $batchsize): array {
        global $DB;
        if ($batchsize <= 0) {
            return [];
        }
        $rows = [];
        $transaction = $DB->start_delegated_transaction();
        try {
            $rows = $DB->get_records(
                'block_grade_me_sla_queue',
                null,
                'timeenqueued ASC, id ASC',
                '*',
                0,
                $batchsize
            );
            if (!empty($rows)) {
                [$insql, $inparams] = $DB->get_in_or_equal(array_keys($rows));
                $DB->delete_records_select('block_grade_me_sla_queue', "id $insql", $inparams);
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            throw $e;
        }
        return array_values($rows);
    }

    /**
     * Current queue depth.
     *
     * @return int
     */
    public static function size(): int {
        global $DB;
        return (int) $DB->count_records('block_grade_me_sla_queue');
    }
}

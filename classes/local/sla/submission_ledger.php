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
 * Per-submission ledger service.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\local\sla;

defined('MOODLE_INTERNAL') || die();

/**
 * Reads from {assign_submission} + {assign_grades} + {assign} + {course_modules}
 * and upserts a canonical row into {block_grade_me_sla_sub}, keyed by
 * (cmid, userid, attemptnumber). Each upsert enqueues the (course, group, modtype)
 * tuple in the dirty queue so the drain task can recompute the rollup.
 *
 * The same method is the canonical entry point for both the event observer
 * (cheap: one row at a time) and the backfill task (bulk: walks assign_submission
 * in chunks). Idempotency comes from the (cmid, userid, attemptnumber) unique
 * index — calling twice with the same data leaves a single row.
 */
class submission_ledger {

    /**
     * Refresh the ledger row for one (cmid, userid, attemptnumber) tuple.
     *
     * No-op when:
     *  - the cmid is not an assign module
     *  - no {assign_submission} row exists for the tuple (we only track once
     *    a user has actually submitted at least once)
     *  - the course is hidden and the admin hasn't opted into hidden-course
     *    calculations (`block_grade_me_enableshowhidden` is 0). Hidden courses
     *    contribute *nothing* — no ledger writes, no rollup updates, no trend
     *    contributions. When the admin later flips the setting on, run Reset
     *    to backfill from scratch.
     *
     * @param int $cmid course module id
     * @param int $userid
     * @param int $attemptnumber
     * @return bool true when a row was written, false when skipped
     */
    public static function upsert_for_cm_user_attempt(int $cmid, int $userid, int $attemptnumber): bool {
        global $DB;

        $sql = "SELECT a.id            AS iteminstance,
                       a.course        AS courseid,
                       c.visible       AS coursevisible,
                       cm.visible      AS cmvisible,
                       sub.id          AS subid,
                       sub.status      AS submissionstatus,
                       sub.timemodified AS timesubmitted,
                       ag.id           AS gradeid,
                       ag.timemodified AS gradetimemodified,
                       ag.grade        AS grade,
                       ag.grader       AS grader
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {assign} a ON a.id = cm.instance
                  JOIN {course} c ON c.id = a.course
             LEFT JOIN {assign_submission} sub
                       ON sub.assignment = a.id
                       AND sub.userid = :subuserid
                       AND sub.attemptnumber = :subattempt
             LEFT JOIN {assign_grades} ag
                       ON ag.assignment = a.id
                       AND ag.userid = :graduserid
                       AND ag.attemptnumber = :gradattempt
                 WHERE cm.id = :cmid AND m.name = 'assign'";
        $row = $DB->get_record_sql($sql, [
            'cmid'         => $cmid,
            'subuserid'    => $userid,
            'subattempt'   => $attemptnumber,
            'graduserid'   => $userid,
            'gradattempt'  => $attemptnumber,
        ]);

        if (!$row || empty($row->iteminstance) || empty($row->subid)) {
            return false;
        }

        // Course-level visibility gate. When the course is hidden and the
        // admin has not opted into hidden-course calculations, skip the upsert
        // entirely and remove any straggler row from a previous state.
        if (empty($row->coursevisible) && !bucket::include_hidden_courses()) {
            $DB->delete_records('block_grade_me_sla_sub', [
                'cmid'          => $cmid,
                'userid'        => $userid,
                'attemptnumber' => $attemptnumber,
            ]);
            return false;
        }

        $courseid = (int) $row->courseid;
        $groupid = group_resolver::resolve_group_for_user($courseid, $userid);
        $rule = rule_resolver::resolve_rule($cmid, $userid, $groupid);

        // Graded means there's a non-discardable grade from a real teacher.
        $isgraded = !empty($row->gradeid)
            && $row->grade !== null
            && (float) $row->grade >= 0
            && (int) $row->grader > 0;
        $timegraded = $isgraded ? (int) $row->gradetimemodified : null;
        $timesubmitted = (int) $row->timesubmitted;

        // Reference SQL: when the submission changed AFTER the grading event the
        // "waiting time" is undefined — the teacher is now looking at a fresh
        // version. Treat as ungraded.
        if ($timegraded !== null && $timegraded < $timesubmitted) {
            $timegraded = null;
        }

        $waitinghours = null;
        if ($timegraded !== null && $timesubmitted > 0) {
            $waitinghours = round(max(0, $timegraded - $timesubmitted) / 3600.0, 2);
        }

        $now = time();
        $existing = $DB->get_record(
            'block_grade_me_sla_sub',
            ['cmid' => $cmid, 'userid' => $userid, 'attemptnumber' => $attemptnumber],
            'id'
        );

        $record = (object) [
            'courseid'         => $courseid,
            'groupid'          => $groupid,
            'cmid'             => $cmid,
            'modtype'          => 'assign',
            'iteminstance'     => (int) $row->iteminstance,
            'userid'           => $userid,
            'attemptnumber'    => $attemptnumber,
            'submissionstatus' => (string) $row->submissionstatus,
            'timesubmitted'    => $timesubmitted,
            'timegraded'       => $timegraded,
            'waitinghours'     => $waitinghours,
            'hasrule'          => $rule['has_rule'] ? 1 : 0,
            'timeopens'        => $rule['opens_at'],
            'timecloses'       => $rule['closes_at'],
            'timemodified'     => $now,
        ];

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('block_grade_me_sla_sub', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record('block_grade_me_sla_sub', $record);
        }

        dirty_queue::enqueue($courseid, $groupid, 'assign');

        return true;
    }

    /**
     * Re-resolve the rule for every ledger row attributed to (assignid, groupid).
     * Called by the group_override_(created|updated|deleted) observers.
     *
     * @param int $assignid {assign}.id
     * @param int $groupid
     * @return int number of ledger rows touched
     */
    public static function re_resolve_rules_for_assign_group(int $assignid, int $groupid): int {
        global $DB;
        $rows = $DB->get_records(
            'block_grade_me_sla_sub',
            ['iteminstance' => $assignid, 'groupid' => $groupid, 'modtype' => 'assign'],
            '',
            'id, cmid, userid, attemptnumber'
        );
        $count = 0;
        foreach ($rows as $r) {
            if (self::upsert_for_cm_user_attempt((int) $r->cmid, (int) $r->userid, (int) $r->attemptnumber)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Drop ledger rows for a deleted course module + its rollup/trend rows.
     *
     * @param int $cmid
     */
    public static function delete_for_cm(int $cmid): void {
        global $DB;
        // We need the affected (course, group) tuples first so we can re-enqueue
        // them — deleting these submissions changes their pending count.
        $affected = $DB->get_records_sql(
            "SELECT DISTINCT courseid, groupid, modtype
               FROM {block_grade_me_sla_sub}
              WHERE cmid = :cmid",
            ['cmid' => $cmid]
        );
        $DB->delete_records('block_grade_me_sla_sub', ['cmid' => $cmid]);
        foreach ($affected as $row) {
            dirty_queue::enqueue((int) $row->courseid, (int) $row->groupid, (string) $row->modtype);
        }
    }

    /**
     * Drop all ledger / rollup / trend rows for a course (course deletion event).
     *
     * @param int $courseid
     */
    public static function delete_for_course(int $courseid): void {
        global $DB;
        $DB->delete_records('block_grade_me_sla_sub',   ['courseid' => $courseid]);
        $DB->delete_records('block_grade_me_sla_group', ['courseid' => $courseid]);
        $DB->delete_records('block_grade_me_sla_trend', ['courseid' => $courseid]);
        $DB->delete_records('block_grade_me_sla_queue', ['courseid' => $courseid]);
    }
}

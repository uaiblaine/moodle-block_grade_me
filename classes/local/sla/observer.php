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
 * SLA event observer.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\local\sla;

defined('MOODLE_INTERNAL') || die();

/**
 * Thin observers that turn assign-related events into ledger upserts and
 * dirty-queue entries. All heavy lifting (stats, percentile, rule resolution)
 * happens later in the drain task; the observer's job is just to record that
 * something changed.
 *
 * Each handler is idempotent: replaying the same event leaves the same row.
 * The ledger key (cmid, userid, attemptnumber) is the unique handle.
 */
class observer {

    /**
     * Handles events that change a submission's state without grading it
     * (assessable_submitted, submission_status_updated, the two
     * assignsubmission_* submission_created events).
     *
     * @param \core\event\base $event
     */
    public static function submission_changed(\core\event\base $event): void {
        $cmid = (int) $event->contextinstanceid;
        if ($cmid <= 0) {
            return;
        }
        $submissionid = (int) ($event->objectid ?? 0);
        if ($submissionid <= 0) {
            return;
        }

        global $DB;
        $sub = $DB->get_record('assign_submission', ['id' => $submissionid], 'userid, attemptnumber');
        if (!$sub) {
            return;
        }
        submission_ledger::upsert_for_cm_user_attempt(
            $cmid,
            (int) $sub->userid,
            (int) $sub->attemptnumber
        );
    }

    /**
     * Handles \mod_assign\event\submission_graded. The event's objectid is the
     * {assign_grades}.id; we look up userid and attemptnumber from there.
     *
     * @param \core\event\base $event
     */
    public static function submission_graded(\core\event\base $event): void {
        $cmid = (int) $event->contextinstanceid;
        if ($cmid <= 0) {
            return;
        }
        $gradeid = (int) ($event->objectid ?? 0);
        if ($gradeid <= 0) {
            return;
        }

        global $DB;
        $grade = $DB->get_record('assign_grades', ['id' => $gradeid], 'userid, attemptnumber');
        if (!$grade) {
            return;
        }
        $written = submission_ledger::upsert_for_cm_user_attempt(
            $cmid,
            (int) $grade->userid,
            (int) $grade->attemptnumber
        );
        if (!$written) {
            return;
        }

        // Grading is the highest-impact event: queue an adhoc task that
        // recomputes the affected rollup + purges the WS cache on the next
        // cron tick, instead of waiting up to five minutes for sla_drain.
        // `$checkforexisting = true` collapses bursts of grading events on the
        // same (course, group) into a single queued task.
        $tuple = $DB->get_record(
            'block_grade_me_sla_sub',
            ['cmid' => $cmid, 'userid' => (int) $grade->userid, 'attemptnumber' => (int) $grade->attemptnumber],
            'courseid, groupid, modtype'
        );
        if ($tuple) {
            $task = new \block_grade_me\task\sla_recompute_one();
            $task->set_custom_data([
                'courseid' => (int) $tuple->courseid,
                'groupid'  => (int) $tuple->groupid,
                'modtype'  => (string) $tuple->modtype,
            ]);
            \core\task\manager::queue_adhoc_task($task, true);
        }
    }

    /**
     * Handles all three \mod_assign\event\group_override_* events. The override
     * payload carries assignid and groupid in $other.
     *
     * @param \core\event\base $event
     */
    public static function override_changed(\core\event\base $event): void {
        $other = $event->other;
        if (is_object($other)) {
            $other = (array) $other;
        }
        $assignid = (int) ($other['assignid'] ?? 0);
        $groupid  = (int) ($other['groupid']  ?? 0);
        if ($assignid <= 0 || $groupid <= 0) {
            return;
        }
        submission_ledger::re_resolve_rules_for_assign_group($assignid, $groupid);
    }

    /**
     * Handles \core\event\course_module_deleted. We only care about assign
     * modules — quiz/forum/etc. are tracked by other observers.
     *
     * @param \core\event\base $event
     */
    public static function course_module_deleted(\core\event\base $event): void {
        $cmid = (int) $event->objectid;
        if ($cmid <= 0) {
            return;
        }
        $other = $event->other;
        if (is_object($other)) {
            $other = (array) $other;
        }
        $modulename = $other['modulename'] ?? null;
        if ($modulename !== null && $modulename !== 'assign') {
            return;
        }
        submission_ledger::delete_for_cm($cmid);
    }

    /**
     * Handles \core\event\course_deleted. Drops all SLA rows for the course.
     *
     * @param \core\event\base $event
     */
    public static function course_deleted(\core\event\base $event): void {
        $courseid = (int) $event->objectid;
        if ($courseid <= 0) {
            return;
        }
        submission_ledger::delete_for_course($courseid);
    }
}

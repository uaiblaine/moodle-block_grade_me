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
 * External web service: Grading Responsiveness payload for one course.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\external;

defined('MOODLE_INTERNAL') || die();

use block_grade_me\local\sla\bucket;
use block_grade_me\local\sla\rule_resolver;
use block_grade_me\local\sla\stats;
use block_grade_me\local\sla\trend_service;
use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use moodle_url;

/**
 * Returns the per-group Grading Responsiveness payload for one course as a
 * structured JSON document. The AMD module composes the card DOM from this.
 *
 * The response is cached per (userid, courseid) for 15 minutes via the
 * `responsiveness_payload` cache definition.
 */
class get_responsiveness extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * @param int $courseid
     * @return array
     */
    public static function execute(int $courseid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);
        $courseid = (int) $params['courseid'];

        $context = context_course::instance($courseid);
        self::validate_context($context);
        require_capability('block/grade_me:viewresponsiveness', $context);

        $showcompare = has_capability('block/grade_me:viewschoolaverage', $context)
            && (int) get_config('block_grade_me', 'show_school_comparison') !== 0;

        $cache = \cache::make('block_grade_me', 'responsiveness_payload');
        $cachekey = $USER->id . '_' . $courseid;
        $hit = $cache->get($cachekey);
        if (is_array($hit) && isset($hit['payload'], $hit['lastsynced'])) {
            $payload = $hit['payload'];
            $payload['lastsynced'] = (int) $hit['lastsynced'];
            return $payload;
        }

        $course = get_course($courseid);
        $coursename = format_string($course->fullname ?? $course->shortname ?? '');

        // Groups in scope: by default the teacher's own memberships. When an
        // admin has `enableadminviewall = 1` we expand to every group in the
        // course (matches the activity-list behaviour).
        if (bucket::admin_views_all()) {
            $allgroups = groups_get_all_groups($courseid);
            $groupids = array_map('intval', array_keys($allgroups));
        } else {
            $usergroups = groups_get_user_groups($courseid, $USER->id);
            // groups_get_user_groups returns [groupingid => [groupid, ...], ...];
            // flatten and dedupe.
            $groupids = [];
            if (!empty($usergroups)) {
                foreach ($usergroups as $gids) {
                    foreach ($gids as $gid) {
                        $groupids[(int) $gid] = true;
                    }
                }
            }
            $groupids = array_keys($groupids);
        }

        $groupspayload = [];
        foreach ($groupids as $groupid) {
            $groupspayload[] = self::build_group_payload(
                $courseid,
                $coursename,
                (int) $groupid,
                $showcompare
            );
        }

        $now = time();
        $payload = [
            'success'     => true,
            'courseid'    => $courseid,
            'groups'      => $groupspayload,
            'lastsynced'  => $now,
            'refreshable' => true,
        ];
        $cache->set($cachekey, ['payload' => $payload, 'lastsynced' => $now]);
        return $payload;
    }

    /**
     * Build the payload for one group.
     *
     * @param int $courseid
     * @param string $coursename Pre-formatted
     * @param int $groupid
     * @param bool $showcompare
     * @return array
     */
    private static function build_group_payload(
        int $courseid,
        string $coursename,
        int $groupid,
        bool $showcompare
    ): array {
        global $DB;

        $group = $DB->get_record('groups', ['id' => $groupid], 'id, name');
        $groupname = $group ? format_string($group->name) : '';

        $rollup = $DB->get_record('block_grade_me_sla_group', [
            'courseid' => $courseid,
            'groupid'  => $groupid,
            'modtype'  => 'assign',
        ]);

        $medianh = $rollup ? self::nullable_float($rollup->medianh) : null;
        $p90h    = $rollup ? self::nullable_float($rollup->p90h) : null;
        $maxh    = $rollup ? self::nullable_float($rollup->maxh) : null;
        $pending  = $rollup ? (int) $rollup->pending  : 0;
        $critical = $rollup ? (int) $rollup->critical : 0;
        $overgoal = $rollup ? (int) $rollup->overgoal : 0;

        $thresholds = bucket::parse_thresholds(
            get_config('block_grade_me', 'sla_thresholds') ?: null
        );
        $bucketid = bucket::bucket_for($medianh, $thresholds);

        [$series, $trendpct] = self::trend_for($courseid, $groupid, 'assign');

        $compare = null;
        if ($showcompare) {
            $compare = self::compare_for('assign');
        }

        $activities = self::activities_for($courseid, $groupid, 'assign');

        $reporturl   = new moodle_url('/blocks/grade_me/report.php',   ['group' => $groupid]);
        $pendingurl  = new moodle_url('/blocks/grade_me/report.php',   ['group' => $groupid, 'filter' => 'pending']);
        $criticalurl = new moodle_url('/blocks/grade_me/report.php',   ['group' => $groupid, 'filter' => 'critical']);

        return [
            'groupid'      => $groupid,
            'groupname'    => $groupname,
            'coursename'   => $coursename,
            'median_h'     => $medianh,
            'p90_h'        => $p90h,
            'max_h'        => $maxh,
            'pending'      => $pending,
            'critical'     => $critical,
            'over_goal'    => $overgoal,
            'bucket'       => $bucketid,
            'trend_pct'    => $trendpct,
            'trend_series' => $series,
            'compare'      => $compare,
            'activities'   => $activities,
            'report_url'   => $reporturl->out(false),
            'pending_url'  => $pendingurl->out(false),
            'critical_url' => $criticalurl->out(false),
        ];
    }

    /**
     * Build the 30-day sparkline series + percentage delta vs previous 15-day window.
     *
     * @param int $courseid
     * @param int $groupid
     * @param string $modtype
     * @return array{0:float[],1:float} [series, trend_pct]
     */
    private static function trend_for(int $courseid, int $groupid, string $modtype): array {
        global $DB;

        $today = trend_service::day_key(time());
        $start = trend_service::day_key(time() - 29 * 86400);
        // Pull all the days in the window in one query, then fill gaps with 0.
        $rows = $DB->get_records_sql(
            "SELECT day, medianh FROM {block_grade_me_sla_trend}
             WHERE courseid = :courseid AND groupid = :groupid
               AND modtype = :modtype AND day >= :start AND day <= :end
             ORDER BY day ASC",
            [
                'courseid' => $courseid,
                'groupid'  => $groupid,
                'modtype'  => $modtype,
                'start'    => $start,
                'end'      => $today,
            ]
        );
        $bydate = [];
        foreach ($rows as $r) {
            $bydate[(int) $r->day] = self::nullable_float($r->medianh) ?? 0.0;
        }

        // Build a 30-entry series. Days with no graded items render as 0.
        $series = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = trend_service::day_key(time() - $i * 86400);
            $series[] = $bydate[$day] ?? 0.0;
        }

        // Trend pct = (median(recent 15) - median(prev 15)) / median(prev 15) * 100.
        $recent = array_slice($series, 15);
        $previous = array_slice($series, 0, 15);
        $r = stats::median(array_values(array_filter($recent, fn($v) => $v > 0)));
        $p = stats::median(array_values(array_filter($previous, fn($v) => $v > 0)));
        $trendpct = 0.0;
        if ($p !== null && $p > 0 && $r !== null) {
            $trendpct = round((($r - $p) / $p) * 100.0, 1);
        }

        return [$series, $trendpct];
    }

    /**
     * Read the most recent site row for $modtype.
     *
     * @param string $modtype
     * @return array{school_median:float,top10:float}|null
     */
    private static function compare_for(string $modtype): ?array {
        $site = \block_grade_me\local\sla\site_stats_service::latest($modtype);
        if (!$site) {
            return null;
        }
        return [
            'school_median' => self::nullable_float($site->medianh) ?? 0.0,
            'top10'         => self::nullable_float($site->p10h) ?? 0.0,
        ];
    }

    /**
     * Build the activities row for a card.
     *
     * Lists every assignment in the course (up to 5, due-date-asc) and resolves
     * the Open/Close rule for the requested group. The pending count is read
     * from the ledger so the row shows both the institutional rule status and
     * the live workload. Activities show even on a "clean" (zero-pending) group
     * so the Rule / No-Rule strip is always visible.
     *
     * @param int $courseid
     * @param int $groupid
     * @param string $modtype
     * @return array[]
     */
    private static function activities_for(int $courseid, int $groupid, string $modtype): array {
        global $DB;

        $sql = "SELECT a.id AS iteminstance, a.name, cm.id AS cmid
                  FROM {assign} a
                  JOIN {course_modules} cm ON cm.instance = a.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                 WHERE a.course = :courseid
                   AND cm.deletioninprogress = 0";
        if (!bucket::include_hidden_activities()) {
            $sql .= " AND cm.visible = 1";
        }
        $sql .= " ORDER BY CASE WHEN a.duedate IS NULL OR a.duedate = 0 THEN 1 ELSE 0 END ASC,
                       a.duedate ASC,
                       a.name ASC";
        // No LIMIT — we want every activity in the course so the Open/Close
        // rule status is visible for all of them, not just the next five.
        // Per-activity cost is two extra reads (rule + pending count); the
        // 15-min WS cache absorbs the round-trip.
        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid]);

        $activities = [];
        foreach ($rows as $r) {
            // resolve_rule with userid=0 skips the per-user override gate so we
            // see the rule that applies to the *group*, not to any specific student.
            $rule = rule_resolver::resolve_rule((int) $r->cmid, 0, $groupid);
            $pending = self::pending_count_for_cm($courseid, $groupid, (int) $r->cmid, $modtype);
            $overrideurl = new moodle_url('/mod/assign/overrides.php', [
                'cmid' => (int) $r->cmid,
                'mode' => 'group',
            ]);
            $activities[] = [
                'cmid'          => (int) $r->cmid,
                'name'          => format_string($r->name ?? ''),
                'opens_at'      => $rule['opens_at'],
                'closes_at'     => $rule['closes_at'],
                'cutoff_at'     => $rule['cutoff_at'],
                'urgency'       => $rule['urgency'],
                'has_rule'      => (bool) $rule['has_rule'],
                'pending_count' => $pending,
                'override_url'  => $overrideurl->out(false),
            ];
        }
        return $activities;
    }

    /**
     * Count pending submissions for one (courseid, groupid, cmid, modtype) tuple,
     * honouring `block_grade_me_maxage`.
     *
     * @param int $courseid
     * @param int $groupid
     * @param int $cmid
     * @param string $modtype
     * @return int
     */
    private static function pending_count_for_cm(int $courseid, int $groupid, int $cmid, string $modtype): int {
        global $DB;
        $sql = "SELECT COUNT(*)
                  FROM {block_grade_me_sla_sub} sub
                  JOIN {course_modules} cm ON cm.id = sub.cmid
                 WHERE sub.courseid = :courseid
                   AND sub.groupid = :groupid
                   AND sub.cmid = :cmid
                   AND sub.modtype = :modtype
                   AND sub.submissionstatus = 'submitted'
                   AND sub.timegraded IS NULL";
        if (!bucket::include_hidden_activities()) {
            $sql .= " AND cm.visible = 1";
        }
        $params = [
            'courseid' => $courseid,
            'groupid'  => $groupid,
            'cmid'     => $cmid,
            'modtype'  => $modtype,
        ];
        $maxagecutoff = bucket::maxage_cutoff();
        if ($maxagecutoff !== null) {
            $sql .= " AND sub.timesubmitted >= :maxagecutoff";
            $params['maxagecutoff'] = $maxagecutoff;
        }
        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Convert a stringy / null DB float into a typed float or null.
     *
     * @param mixed $v
     * @return float|null
     */
    private static function nullable_float($v): ?float {
        if ($v === null || $v === '') {
            return null;
        }
        return (float) $v;
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $compare = new external_single_structure([
            'school_median' => new external_value(PARAM_FLOAT, 'Site-wide median waiting hours yesterday'),
            'top10'         => new external_value(PARAM_FLOAT, '10th percentile (top 10% fastest) yesterday'),
        ], 'School comparison, null when capability missing', VALUE_OPTIONAL, null, NULL_ALLOWED);

        $activity = new external_single_structure([
            'cmid'          => new external_value(PARAM_INT, 'Course module id'),
            'name'          => new external_value(PARAM_RAW, 'Activity name'),
            'opens_at'      => new external_value(PARAM_INT, 'Effective open ts (allowsubmissionsfromdate)', VALUE_DEFAULT, null, NULL_ALLOWED),
            'closes_at'     => new external_value(PARAM_INT, 'Effective due ts (duedate)',                     VALUE_DEFAULT, null, NULL_ALLOWED),
            'cutoff_at'     => new external_value(PARAM_INT, 'Effective cutoff/deadline ts (cutoffdate)',      VALUE_DEFAULT, null, NULL_ALLOWED),
            'urgency'       => new external_value(PARAM_ALPHAEXT, 'on-track|soon|urgent|overdue|none'),
            'has_rule'      => new external_value(PARAM_BOOL, 'Whether an open/due/cutoff rule applies'),
            'pending_count' => new external_value(PARAM_INT, 'Pending submissions for this activity in this group'),
            'override_url'  => new external_value(PARAM_URL, 'URL of the group-overrides page for this activity'),
        ]);

        $group = new external_single_structure([
            'groupid'      => new external_value(PARAM_INT, 'Group id'),
            'groupname'    => new external_value(PARAM_RAW, 'Group name'),
            'coursename'   => new external_value(PARAM_RAW, 'Course name (kicker)'),
            'median_h'     => new external_value(PARAM_FLOAT, 'Median waiting hours', VALUE_DEFAULT, null, NULL_ALLOWED),
            'p90_h'        => new external_value(PARAM_FLOAT, 'P90 waiting hours', VALUE_DEFAULT, null, NULL_ALLOWED),
            'max_h'        => new external_value(PARAM_FLOAT, 'Max waiting hours', VALUE_DEFAULT, null, NULL_ALLOWED),
            'pending'      => new external_value(PARAM_INT, 'Pending count'),
            'critical'     => new external_value(PARAM_INT, 'Critical count'),
            'over_goal'    => new external_value(PARAM_INT, 'Count over the goal'),
            'bucket'       => new external_value(PARAM_ALPHA, 'Bucket id (excellent|good|regular|critical)'),
            'trend_pct'    => new external_value(PARAM_FLOAT, '30-day delta percentage'),
            'trend_series' => new external_multiple_structure(
                new external_value(PARAM_FLOAT, 'Daily median for a day in the 30-day window')
            ),
            'compare'      => $compare,
            'activities'   => new external_multiple_structure($activity),
            'report_url'   => new external_value(PARAM_URL, 'Base URL of the detailed report'),
            'pending_url'  => new external_value(PARAM_URL, 'Report URL pre-filtered to Pending'),
            'critical_url' => new external_value(PARAM_URL, 'Report URL pre-filtered to Critical'),
        ]);

        return new external_single_structure([
            'success'     => new external_value(PARAM_BOOL, 'Always true on a non-exception response'),
            'courseid'    => new external_value(PARAM_INT, 'Course id echoed back'),
            'groups'      => new external_multiple_structure($group),
            'lastsynced'  => new external_value(PARAM_INT, 'Unix timestamp when this payload was computed'),
            'refreshable' => new external_value(PARAM_BOOL, 'True when the WS supports an explicit refresh'),
        ]);
    }
}

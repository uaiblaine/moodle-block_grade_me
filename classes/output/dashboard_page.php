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
 * Teacher Dashboard page templatable.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\output;

defined('MOODLE_INTERNAL') || die();

use block_grade_me\local\sla\bucket;
use block_grade_me\local\sla\rule_resolver;
use block_grade_me\local\sla\stats;
use block_grade_me\local\sla\trend_service;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Shapes data for templates/dashboard.mustache. Pulls the teacher's group
 * rollups from {block_grade_me_sla_group}, aggregates per-course summaries,
 * and queries the ledger for the top-priority pending submissions.
 */
class dashboard_page implements renderable, templatable {

    /** @var int */
    private $userid;
    /** @var string */
    private $sort;

    public const SORT_CRITICAL_FIRST = 'critical_first';
    public const SORT_PENDING        = 'pending';
    public const SORT_MEDIAN         = 'median';
    public const SORT_TITLE          = 'title';

    public const PRIORITY_CARD_COUNT = 3;
    public const CRITICAL_HOURS = 120;

    /**
     * @param int $userid
     * @param string $sort one of the SORT_* constants
     */
    public function __construct(int $userid, string $sort = self::SORT_CRITICAL_FIRST) {
        $this->userid = $userid;
        $this->sort = in_array($sort, self::valid_sorts(), true) ? $sort : self::SORT_CRITICAL_FIRST;
    }

    /**
     * @return string[]
     */
    public static function valid_sorts(): array {
        return [self::SORT_CRITICAL_FIRST, self::SORT_PENDING, self::SORT_MEDIAN, self::SORT_TITLE];
    }

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB, $USER;

        $user = $DB->get_record('user', ['id' => $this->userid], '*', MUST_EXIST);
        $greeting = get_string('dashboard_greeting', 'block_grade_me', [
            'firstname' => format_string($user->firstname),
        ]);

        $rollups = $this->fetch_teacher_rollups();
        $perCourse = $this->aggregate_per_course($rollups);
        $perCourse = $this->apply_sort($perCourse);

        $totalpending = 0;
        $totalcritical = 0;
        $coursemedians = [];
        foreach ($perCourse as $row) {
            $totalpending += $row->pending;
            $totalcritical += $row->critical;
            if ($row->median_h !== null) {
                $coursemedians[] = $row->median_h;
            }
        }
        $overallmedian = stats::median($coursemedians);

        $thresholds = bucket::parse_thresholds(get_config('block_grade_me', 'sla_thresholds') ?: null);

        $courserows = [];
        foreach ($perCourse as $row) {
            $bucketid = bucket::bucket_for($row->median_h, $thresholds);
            $courserows[] = [
                'courseid'    => $row->courseid,
                'coursename'  => format_string($row->coursename),
                'courseurl'   => (new moodle_url('/course/view.php', ['id' => $row->courseid]))->out(false),
                'pending'     => $row->pending,
                'critical'    => $row->critical,
                'median_h'    => self::fmt_hours($row->median_h),
                'bucket'      => $bucketid,
                'bucket_color' => bucket::bucket_color($bucketid),
                'bucket_label' => get_string(bucket::bucket_label_key($bucketid), 'block_grade_me'),
                'sparkline'   => $output->render_sparkline($row->trend_series, [
                    'width' => 90,
                    'height' => 24,
                    'color' => bucket::bucket_color($bucketid),
                ]),
                'sla_bar'     => $output->render_sla_bar($row->median_h, ['height' => 6]),
            ];
        }

        $prioritycards = $this->fetch_priority_cards($output, $thresholds);

        return [
            'greeting'        => $greeting,
            'pluginname'      => get_string('pluginname', 'block_grade_me'),
            'subtitle'        => get_string('dashboard_subtitle', 'block_grade_me', [
                'pending' => $totalpending,
                'overgoal' => max(0, $totalpending - count($prioritycards)),
            ]),
            'mini_stats'      => [
                ['label' => get_string('dashboard_active_courses', 'block_grade_me'),
                 'value' => count($perCourse)],
                ['label' => get_string('dashboard_median_wait', 'block_grade_me'),
                 'value' => self::fmt_hours($overallmedian)],
                ['label' => get_string('dashboard_critical_total', 'block_grade_me'),
                 'value' => $totalcritical],
            ],
            'priority_cards'  => $prioritycards,
            'has_priority'    => !empty($prioritycards),
            'courses'         => $courserows,
            'has_courses'     => !empty($courserows),
            'sort_options'    => $this->sort_options_for_template(),
            'sort_value'      => $this->sort,
            'sort_url'        => (new moodle_url('/blocks/grade_me/dashboard.php'))->out(false),
        ];
    }

    /**
     * Read all (course, group, modtype='assign') rollup rows the teacher is a member of.
     *
     * @return stdClass[]
     */
    private function fetch_teacher_rollups(): array {
        global $DB;
        $params = ['modtype' => 'assign'];
        $where = "sg.modtype = :modtype";
        if (!bucket::include_hidden_courses()) {
            $where .= " AND c.visible = 1";
        }

        // When admin_views_all() the dashboard expands to every group in every
        // (visible) course; otherwise it stays scoped to the user's own group
        // memberships.
        $extrajoin = '';
        if (!bucket::admin_views_all()) {
            $extrajoin = ' JOIN {groups_members} gm ON gm.groupid = g.id ';
            $where .= ' AND gm.userid = :userid';
            $params['userid'] = $this->userid;
        }

        $sql = "SELECT sg.id, sg.courseid, sg.groupid, sg.pending, sg.critical,
                       sg.overgoal, sg.medianh AS median_h, sg.p90h AS p90_h, sg.maxh AS max_h,
                       c.fullname AS coursename, g.name AS groupname
                  FROM {block_grade_me_sla_group} sg
                  JOIN {groups} g ON g.id = sg.groupid
                  $extrajoin
                  JOIN {course} c ON c.id = sg.courseid
                 WHERE $where";
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * @param stdClass[] $rollups
     * @return stdClass[] indexed by courseid
     */
    private function aggregate_per_course(array $rollups): array {
        $byCourse = [];
        $medians = [];
        foreach ($rollups as $r) {
            $cid = (int) $r->courseid;
            if (!isset($byCourse[$cid])) {
                $byCourse[$cid] = (object) [
                    'courseid'   => $cid,
                    'coursename' => $r->coursename,
                    'pending'    => 0,
                    'critical'   => 0,
                    'overgoal'   => 0,
                    'median_h'   => null,
                    'trend_series' => [],
                ];
                $medians[$cid] = [];
            }
            $byCourse[$cid]->pending  += (int) $r->pending;
            $byCourse[$cid]->critical += (int) $r->critical;
            $byCourse[$cid]->overgoal += (int) $r->overgoal;
            if ($r->median_h !== null) {
                $medians[$cid][] = (float) $r->median_h;
            }
        }
        foreach ($byCourse as $cid => $row) {
            $row->median_h = stats::median($medians[$cid]);
            $row->trend_series = $this->fetch_course_trend($cid);
        }
        return $byCourse;
    }

    /**
     * 30-day median-of-medians sparkline for one course across the teacher's groups.
     *
     * @param int $courseid
     * @return float[]
     */
    private function fetch_course_trend(int $courseid): array {
        global $DB;
        $start = trend_service::day_key(time() - 29 * 86400);
        $today = trend_service::day_key(time());
        $sql = "SELECT t.day, t.medianh
                  FROM {block_grade_me_sla_trend} t
                  JOIN {groups_members} gm ON gm.groupid = t.groupid
                 WHERE t.courseid = :courseid AND t.modtype = :modtype
                   AND gm.userid = :userid
                   AND t.day >= :start AND t.day <= :end
                 ORDER BY t.day ASC";
        $rows = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'modtype'  => 'assign',
            'userid'   => $this->userid,
            'start'    => $start,
            'end'      => $today,
        ]);
        $byday = [];
        foreach ($rows as $r) {
            $d = (int) $r->day;
            if (!isset($byday[$d])) {
                $byday[$d] = [];
            }
            if ($r->medianh !== null) {
                $byday[$d][] = (float) $r->medianh;
            }
        }
        $series = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = trend_service::day_key(time() - $i * 86400);
            $series[] = isset($byday[$day]) ? (stats::median($byday[$day]) ?? 0.0) : 0.0;
        }
        return $series;
    }

    /**
     * Apply the sort dropdown selection to the per-course rows.
     *
     * @param stdClass[] $perCourse
     * @return stdClass[]
     */
    private function apply_sort(array $perCourse): array {
        $rows = array_values($perCourse);
        usort($rows, function ($a, $b) {
            switch ($this->sort) {
                case self::SORT_PENDING:
                    return $b->pending <=> $a->pending;
                case self::SORT_MEDIAN:
                    return ($b->median_h ?? 0) <=> ($a->median_h ?? 0);
                case self::SORT_TITLE:
                    return strcasecmp($a->coursename, $b->coursename);
                case self::SORT_CRITICAL_FIRST:
                default:
                    if ($a->critical === $b->critical) {
                        return ($b->median_h ?? 0) <=> ($a->median_h ?? 0);
                    }
                    return $b->critical <=> $a->critical;
            }
        });
        return $rows;
    }

    /**
     * Build the top-3 priority cards.
     *
     * @param renderer_base $output
     * @param int[] $thresholds
     * @return array[]
     */
    private function fetch_priority_cards(renderer_base $output, array $thresholds): array {
        global $DB;
        $criticalcutoff = time() - self::CRITICAL_HOURS * 3600;
        $where = "sub.modtype = :modtype
                   AND sub.submissionstatus = 'submitted'
                   AND sub.timegraded IS NULL";
        $params = [
            'modtype'  => 'assign',
            'critcut'  => $criticalcutoff,
        ];
        $maxagecutoff = bucket::maxage_cutoff();
        if ($maxagecutoff !== null) {
            $where .= " AND sub.timesubmitted >= :maxagecutoff";
            $params['maxagecutoff'] = $maxagecutoff;
        }
        if (!bucket::include_hidden_courses()) {
            $where .= " AND c.visible = 1";
        }
        if (!bucket::include_hidden_activities()) {
            $where .= " AND cm.visible = 1";
        }

        // Admins with viewall see every pending submission across every
        // visible group; everyone else is scoped to their own memberships.
        $memberjoin = '';
        if (!bucket::admin_views_all()) {
            $memberjoin = ' JOIN {groups_members} gm ON gm.groupid = sub.groupid AND gm.userid = :memberid ';
            $params['memberid'] = $this->userid;
        }

        $sql = "SELECT sub.id AS ledgerid, sub.cmid, sub.userid, sub.timesubmitted, sub.timecloses,
                       sub.courseid, sub.groupid,
                       a.name AS activityname, c.fullname AS coursename,
                       u.firstname, u.lastname,
                       u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
                  FROM {block_grade_me_sla_sub} sub
                  $memberjoin
                  JOIN {assign} a ON a.id = sub.iteminstance
                  JOIN {course_modules} cm ON cm.id = sub.cmid
                  JOIN {course} c ON c.id = sub.courseid
                  JOIN {user} u ON u.id = sub.userid
                 WHERE $where
              ORDER BY CASE WHEN sub.timesubmitted <= :critcut THEN 0 ELSE 1 END ASC,
                       sub.timesubmitted ASC,
                       sub.timecloses ASC";
        $rows = $DB->get_records_sql($sql, $params, 0, self::PRIORITY_CARD_COUNT);

        $now = time();
        $cards = [];
        $rank = 1;
        foreach ($rows as $r) {
            $waitinghours = max(0.0, ($now - (int) $r->timesubmitted) / 3600.0);
            $bucketid = bucket::bucket_for($waitinghours, $thresholds);
            $gradelink = new moodle_url('/mod/assign/view.php', [
                'id'      => (int) $r->cmid,
                'action'  => 'grading',
                'userid'  => (int) $r->userid,
            ]);
            $initials = strtoupper(mb_substr($r->firstname, 0, 1) . mb_substr($r->lastname, 0, 1));
            $cards[] = [
                'rank'           => $rank++,
                'activityname'   => format_string($r->activityname),
                'coursename'     => format_string($r->coursename),
                'studentname'    => fullname($r),
                'avatar'         => $output->render_avatar($initials, ['size' => 30]),
                'waiting_hours'  => self::fmt_hours($waitinghours),
                'bucket'         => $bucketid,
                'bucket_color'   => bucket::bucket_color($bucketid),
                'bucket_label'   => get_string(bucket::bucket_label_key($bucketid), 'block_grade_me'),
                'sla_bar'        => $output->render_sla_bar($waitinghours, ['height' => 6]),
                'gradelink'      => $gradelink->out(false),
                'closes_label'   => $this->closes_label($r->timecloses, $now),
            ];
        }
        return $cards;
    }

    /**
     * Build a short contextual note: "Closes today", "Overdue", or "".
     *
     * @param int|null $closesat
     * @param int $now
     * @return string
     */
    private function closes_label(?int $closesat, int $now): string {
        if (!$closesat) {
            return '';
        }
        $secondsleft = (int) $closesat - $now;
        if ($secondsleft < 0) {
            return get_string('dashboard_overdue', 'block_grade_me');
        }
        if ($secondsleft < 86400) {
            return get_string('dashboard_closes_today', 'block_grade_me');
        }
        if ($secondsleft < 3 * 86400) {
            return get_string('dashboard_closes_soon', 'block_grade_me');
        }
        return '';
    }

    /**
     * @return array[]
     */
    private function sort_options_for_template(): array {
        $opts = [];
        foreach (self::valid_sorts() as $sort) {
            $opts[] = [
                'value'    => $sort,
                'label'    => get_string('dashboard_sort_' . $sort, 'block_grade_me'),
                'selected' => $sort === $this->sort,
            ];
        }
        return $opts;
    }

    /**
     * Pretty-print an hour count for display.
     *
     * @param float|null $h
     * @return string
     */
    private static function fmt_hours(?float $h): string {
        if ($h === null) {
            return '—';
        }
        if ($h < 10) {
            return rtrim(rtrim(number_format($h, 1, '.', ''), '0'), '.') . 'h';
        }
        return (int) round($h) . 'h';
    }
}

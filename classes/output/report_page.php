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
 * Detailed Report page templatable.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\output;

defined('MOODLE_INTERNAL') || die();

use block_grade_me\local\sla\bucket;
use block_grade_me\local\sla\trend_service;
use moodle_url;
use renderable;
use renderer_base;
use templatable;

/**
 * Shapes data for templates/report.mustache. Reads from {block_grade_me_sla_sub}
 * with a paged query plus the rollup row for the hero metrics.
 */
class report_page implements renderable, templatable {

    public const FILTER_ALL      = 'all';
    public const FILTER_PENDING  = 'pending';
    public const FILTER_CRITICAL = 'critical';

    public const SORT_WAITING  = 'waiting';
    public const SORT_ACTIVITY = 'activity';
    public const SORT_STUDENT  = 'student';

    /** @var int */
    private $groupid;
    /** @var int */
    private $courseid;
    /** @var string */
    private $filter;
    /** @var string */
    private $sort;
    /** @var int */
    private $page;
    /** @var int */
    private $pagesize;

    /**
     * @param int $groupid
     * @param int $courseid
     * @param string $filter
     * @param string $sort
     * @param int $page zero-based page index
     */
    public function __construct(int $groupid, int $courseid, string $filter, string $sort, int $page) {
        $this->groupid = $groupid;
        $this->courseid = $courseid;
        $this->filter = in_array($filter, [self::FILTER_ALL, self::FILTER_PENDING, self::FILTER_CRITICAL], true)
            ? $filter
            : self::FILTER_PENDING;
        $this->sort = in_array($sort, [self::SORT_WAITING, self::SORT_ACTIVITY, self::SORT_STUDENT], true)
            ? $sort
            : self::SORT_WAITING;
        $this->page = max(0, $page);
        $this->pagesize = max(10, (int) (get_config('block_grade_me', 'report_pagesize') ?: 50));
    }

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;

        $group = $DB->get_record('groups', ['id' => $this->groupid], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $this->courseid], '*', MUST_EXIST);
        $rollup = $DB->get_record('block_grade_me_sla_group', [
            'courseid' => $this->courseid,
            'groupid'  => $this->groupid,
            'modtype'  => 'assign',
        ]);

        $medianh = $rollup ? self::nullable_float($rollup->medianh) : null;
        $pending  = $rollup ? (int) $rollup->pending  : 0;
        $critical = $rollup ? (int) $rollup->critical : 0;
        $overgoal = $rollup ? (int) $rollup->overgoal : 0;

        $thresholds = bucket::parse_thresholds(get_config('block_grade_me', 'sla_thresholds') ?: null);
        $goal = (int) (get_config('block_grade_me', 'sla_goal') ?: 24);
        $bucketid = bucket::bucket_for($medianh, $thresholds);

        $trend = $this->fetch_trend_series();
        $distribution = $this->build_distribution($thresholds);

        [$rows, $totalrows] = $this->fetch_table($output, $thresholds);

        $pages = max(1, (int) ceil($totalrows / $this->pagesize));
        $pagination = $this->build_pagination($pages);

        $baseurl = $this->base_url();

        return [
            'breadcrumb_back_url' => (new moodle_url('/blocks/grade_me/dashboard.php'))->out(false),
            'breadcrumb_back'     => get_string('report_breadcrumb_back', 'block_grade_me'),
            'title'               => get_string('report_title', 'block_grade_me'),
            'coursename'          => format_string($course->fullname),
            'groupname'           => format_string($group->name),
            'hero_metrics'        => [
                [
                    'label' => get_string('pending', 'block_grade_me'),
                    'value' => $pending,
                ],
                [
                    'label' => get_string('critical', 'block_grade_me'),
                    'value' => $critical,
                ],
                [
                    'label' => get_string('dashboard_median_wait', 'block_grade_me'),
                    'value' => self::fmt_hours($medianh),
                    'extra' => $output->render_sparkline($trend, [
                        'width' => 110,
                        'height' => 28,
                        'color' => bucket::bucket_color($bucketid),
                    ]),
                ],
                [
                    'label' => get_string('goal_label', 'block_grade_me'),
                    'value' => '< ' . $goal . 'h',
                ],
            ],
            'distribution'        => $distribution,
            'filter_links'        => [
                [
                    'value'    => self::FILTER_ALL,
                    'label'    => get_string('report_filter_all', 'block_grade_me'),
                    'selected' => $this->filter === self::FILTER_ALL,
                    'url'      => $this->base_url()->out(false, ['filter' => self::FILTER_ALL, 'page' => 0]),
                ],
                [
                    'value'    => self::FILTER_PENDING,
                    'label'    => get_string('pending', 'block_grade_me'),
                    'selected' => $this->filter === self::FILTER_PENDING,
                    'url'      => $this->base_url()->out(false, ['filter' => self::FILTER_PENDING, 'page' => 0]),
                ],
                [
                    'value'    => self::FILTER_CRITICAL,
                    'label'    => get_string('critical', 'block_grade_me'),
                    'selected' => $this->filter === self::FILTER_CRITICAL,
                    'url'      => $this->base_url()->out(false, ['filter' => self::FILTER_CRITICAL, 'page' => 0]),
                ],
            ],
            'sort_options'        => [
                ['value' => self::SORT_WAITING,  'label' => get_string('report_sort_waiting', 'block_grade_me'),  'selected' => $this->sort === self::SORT_WAITING],
                ['value' => self::SORT_ACTIVITY, 'label' => get_string('report_sort_activity', 'block_grade_me'), 'selected' => $this->sort === self::SORT_ACTIVITY],
                ['value' => self::SORT_STUDENT,  'label' => get_string('report_sort_student', 'block_grade_me'),  'selected' => $this->sort === self::SORT_STUDENT],
            ],
            'sort_url'            => $baseurl->out(false),
            'sort_state'          => [
                ['name' => 'group',  'value' => $this->groupid],
                ['name' => 'filter', 'value' => $this->filter],
            ],
            'rows'                => $rows,
            'has_rows'            => !empty($rows),
            'pages'               => $pagination,
            'has_pagination'      => $pages > 1,
            'overgoal'            => $overgoal,
        ];
    }

    /**
     * Build the 4-segment distribution bar.
     *
     * @param int[] $thresholds
     * @return array
     */
    private function build_distribution(array $thresholds): array {
        global $DB;
        $distsql = "SELECT sub.id, sub.timesubmitted
                      FROM {block_grade_me_sla_sub} sub
                      JOIN {course_modules} cm ON cm.id = sub.cmid
                     WHERE sub.groupid = :groupid AND sub.courseid = :courseid
                       AND sub.modtype = :modtype
                       AND sub.submissionstatus = 'submitted'
                       AND sub.timegraded IS NULL";
        if (!bucket::include_hidden_activities()) {
            $distsql .= " AND cm.visible = 1";
        }
        $distparams = [
            'groupid'  => $this->groupid,
            'courseid' => $this->courseid,
            'modtype'  => 'assign',
        ];
        $maxagecutoff = bucket::maxage_cutoff();
        if ($maxagecutoff !== null) {
            $distsql .= " AND sub.timesubmitted >= :maxagecutoff";
            $distparams['maxagecutoff'] = $maxagecutoff;
        }
        $rows = $DB->get_records_sql($distsql, $distparams);
        $now = time();
        $counts = [
            bucket::EXCELLENT => 0,
            bucket::GOOD      => 0,
            bucket::REGULAR   => 0,
            bucket::CRITICAL  => 0,
        ];
        foreach ($rows as $r) {
            $hours = max(0.0, ($now - (int) $r->timesubmitted) / 3600.0);
            $counts[bucket::bucket_for($hours, $thresholds)]++;
        }
        $total = max(1, array_sum($counts));
        $out = [];
        foreach ([bucket::EXCELLENT, bucket::GOOD, bucket::REGULAR, bucket::CRITICAL] as $b) {
            $out[] = [
                'bucket' => $b,
                'label'  => get_string(bucket::bucket_label_key($b), 'block_grade_me'),
                'color'  => bucket::bucket_color($b),
                'count'  => $counts[$b],
                'pct'    => round(($counts[$b] / $total) * 100, 1),
            ];
        }
        return $out;
    }

    /**
     * 30-day median sparkline series for this group.
     *
     * @return float[]
     */
    private function fetch_trend_series(): array {
        global $DB;
        $start = trend_service::day_key(time() - 29 * 86400);
        $today = trend_service::day_key(time());
        $rows = $DB->get_records_sql(
            "SELECT day, medianh FROM {block_grade_me_sla_trend}
              WHERE courseid = :courseid AND groupid = :groupid
                AND modtype = :modtype AND day >= :start AND day <= :end
           ORDER BY day ASC",
            [
                'courseid' => $this->courseid,
                'groupid'  => $this->groupid,
                'modtype'  => 'assign',
                'start'    => $start,
                'end'      => $today,
            ]
        );
        $byday = [];
        foreach ($rows as $r) {
            $byday[(int) $r->day] = self::nullable_float($r->medianh) ?? 0.0;
        }
        $series = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = trend_service::day_key(time() - $i * 86400);
            $series[] = $byday[$day] ?? 0.0;
        }
        return $series;
    }

    /**
     * @param renderer_base $output
     * @param int[] $thresholds
     * @return array{0: array[], 1: int} [rows, total]
     */
    private function fetch_table(renderer_base $output, array $thresholds): array {
        global $DB;
        $where = "sub.groupid = :groupid AND sub.modtype = :modtype";
        $params = ['groupid' => $this->groupid, 'modtype' => 'assign'];

        $now = time();
        if ($this->filter === self::FILTER_PENDING || $this->filter === self::FILTER_CRITICAL) {
            $where .= " AND sub.submissionstatus = 'submitted' AND sub.timegraded IS NULL";
        }
        if ($this->filter === self::FILTER_CRITICAL) {
            $where .= " AND sub.timesubmitted <= :critcut";
            $params['critcut'] = $now - bucket::default_thresholds()[2] * 3600;
        }
        $maxagecutoff = bucket::maxage_cutoff();
        if ($maxagecutoff !== null) {
            $where .= " AND sub.timesubmitted >= :maxagecutoff";
            $params['maxagecutoff'] = $maxagecutoff;
        }
        if (!bucket::include_hidden_activities()) {
            $where .= " AND cm.visible = 1";
        }

        $orderby = match ($this->sort) {
            self::SORT_ACTIVITY => 'a.name ASC, sub.timesubmitted ASC',
            self::SORT_STUDENT  => 'u.lastname ASC, u.firstname ASC',
            self::SORT_WAITING  => 'sub.timesubmitted ASC',
            default             => 'sub.timesubmitted ASC',
        };

        $countsql = "SELECT COUNT(*)
                       FROM {block_grade_me_sla_sub} sub
                       JOIN {assign} a ON a.id = sub.iteminstance
                       JOIN {course_modules} cm ON cm.id = sub.cmid
                       JOIN {user} u ON u.id = sub.userid
                      WHERE $where";
        $total = (int) $DB->count_records_sql($countsql, $params);

        $sql = "SELECT sub.id, sub.cmid, sub.userid, sub.timesubmitted, sub.timegraded,
                       sub.waitinghours, sub.timecloses, sub.iteminstance,
                       a.name AS activityname,
                       u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename,
                       CASE WHEN ao.id IS NULL THEN 0 ELSE 1 END AS has_user_override
                  FROM {block_grade_me_sla_sub} sub
                  JOIN {assign} a ON a.id = sub.iteminstance
                  JOIN {course_modules} cm ON cm.id = sub.cmid
                  JOIN {user} u ON u.id = sub.userid
             LEFT JOIN {assign_overrides} ao
                       ON ao.assignid = sub.iteminstance
                       AND ao.userid = sub.userid
                 WHERE $where
              ORDER BY $orderby";
        $records = $DB->get_records_sql($sql, $params, $this->page * $this->pagesize, $this->pagesize);

        $rows = [];
        foreach ($records as $r) {
            $waiting = $r->timegraded
                ? max(0.0, ((int) $r->timegraded - (int) $r->timesubmitted) / 3600.0)
                : max(0.0, ($now - (int) $r->timesubmitted) / 3600.0);
            $bucketid = bucket::bucket_for($waiting, $thresholds);
            $initials = strtoupper(mb_substr($r->firstname, 0, 1) . mb_substr($r->lastname, 0, 1));
            $gradelink = new moodle_url('/mod/assign/view.php', [
                'id'     => (int) $r->cmid,
                'action' => 'grading',
                'userid' => (int) $r->userid,
            ]);
            $rows[] = [
                'studentname'        => fullname($r),
                'avatar'             => $output->render_avatar($initials, ['size' => 26]),
                'activityname'       => format_string($r->activityname),
                'submitted'          => userdate((int) $r->timesubmitted, get_string('strftimedatetimeshort', 'core_langconfig')),
                'waiting_hours'      => self::fmt_hours($waiting),
                'bucket_color'       => bucket::bucket_color($bucketid),
                'sla_bar'            => $output->render_sla_bar($waiting, ['height' => 6]),
                'gradelink'          => $gradelink->out(false),
                'has_user_override'  => (int) $r->has_user_override === 1,
                'override_tooltip'   => get_string('report_user_override_tooltip', 'block_grade_me'),
                'override_label'     => get_string('report_user_override', 'block_grade_me'),
            ];
        }

        return [$rows, $total];
    }

    /**
     * Build a simple pagination array with previous/next links and a list of pages.
     *
     * @param int $totalPages
     * @return array
     */
    private function build_pagination(int $totalPages): array {
        $out = [];
        for ($i = 0; $i < $totalPages; $i++) {
            $url = $this->base_url()->out(false, ['page' => $i]);
            $out[] = [
                'index'    => $i + 1,
                'url'      => $url,
                'selected' => $i === $this->page,
            ];
        }
        return $out;
    }

    /**
     * Base URL preserving the current filter+sort selection.
     *
     * @return moodle_url
     */
    private function base_url(): moodle_url {
        return new moodle_url('/blocks/grade_me/report.php', [
            'group'  => $this->groupid,
            'filter' => $this->filter,
            'sort'   => $this->sort,
        ]);
    }

    /**
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

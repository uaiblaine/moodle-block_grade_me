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
 * Scheduled tasks block_grade_me.
 *
 * @package    block_grade_me
 * @copyright  2017 Derek Henderson {@link http://www.remote-learner.net}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'block_grade_me\task\cache_grade_data',
        'blocking' => 0,
        'minute' => '*/15',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
    [
        'classname' => 'block_grade_me\task\reset_block',
        'blocking' => 0,
        'minute' => '15',
        'hour' => '1',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
    // SLA drain — recompute group rollups for every dirty tuple, every 5 min.
    [
        'classname' => 'block_grade_me\task\sla_drain',
        'blocking'  => 0,
        'minute'    => '*/5',
        'hour'      => '*',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
    // SLA trend rebuild — yesterday's daily medians, once at 03:00.
    [
        'classname' => 'block_grade_me\task\sla_trend_recompute',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '3',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
    // SLA site stats — site-wide median + top-10% for yesterday, at 03:30.
    [
        'classname' => 'block_grade_me\task\sla_site_stats',
        'blocking'  => 0,
        'minute'    => '30',
        'hour'      => '3',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
    // SLA backfill — chunked walk of assign_submission, no-op unless activated
    // by the reset action. Runs every 5 min while active.
    [
        'classname' => 'block_grade_me\task\sla_backfill',
        'blocking'  => 0,
        'minute'    => '*/5',
        'hour'      => '*',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
];

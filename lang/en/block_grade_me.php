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
 * Grade Me block language strings.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['alt_gradebook'] = 'Go to {$a->course_name} gradebook…';
$string['alt_mark'] = 'check';
$string['alt_mod'] = 'Go to {$a->mod_name} in gradebook…';
$string['badge_loading'] = 'Loading ungraded count…';
$string['badge_unknown'] = 'Count unavailable';
$string['datetime'] = '%B %d, %l:%M %p';
$string['excess'] = 'More than {$a->maxcourses} courses have ungraded items — showing the first {$a->maxcourses}.';
$string['expand'] = 'Collapse / Expand All';
$string['grade_me:addinstance'] = 'Add a new Grade Me block';
$string['grade_me:myaddinstance'] = 'Add a new Grade Me block to the My Moodle page';
$string['grade_me:resetdata'] = 'Reset SLA / responsiveness data';
$string['grade_me:viewdashboard'] = 'View the Teacher Dashboard page';
$string['grade_me:viewresponsiveness'] = 'View the Grading Responsiveness section';
$string['grade_me:viewschoolaverage'] = 'See the school median and top-10% comparison';
$string['grade_me_tools'] = 'Tools';
$string['grade_me_tools_desc'] = '<p><a href="{$a}/blocks/grade_me/quiz_update_ngrade.php">Refresh quiz attempts needing grading</a></p>';
$string['lastsynced'] = 'Last synced: {$a->time}';
$string['lastsynced_pending'] = 'Last synced: —';
$string['link_grade_img'] = 'Grade assignment…';
$string['link_gradebook'] = 'Go to {$a->course_name}…';
$string['link_gradebook_icon'] = 'Go to {$a->course_name} gradebook…';
$string['link_mod'] = 'Go to {$a->mod_name}';
$string['link_mod_img'] = 'Go to {$a->mod_name} in gradebook…';
$string['link_user_profile'] = '{$a->first_name}\'s profile…';
$string['nothing'] = 'No ungraded items in your courses.';
$string['pluginname'] = 'Grade Me';
$string['pluginname-reset'] = 'Grade Me - reset table';
$string['privacy:metadata:block_grade_me_quiz_ngrade'] = 'Caches information about quizes needing grades.';
$string['privacy:metadata:block_grade_me_quiz_ngrade:attemptid'] = 'Attempt id';
$string['privacy:metadata:block_grade_me_quiz_ngrade:courseid'] = 'Course id';
$string['privacy:metadata:block_grade_me_quiz_ngrade:questionattemptstepid'] = 'Question Attempt';
$string['privacy:metadata:block_grade_me_quiz_ngrade:quizid'] = 'Quiz id';
$string['privacy:metadata:block_grade_me_quiz_ngrade:userid'] = 'User id';
$string['privacydata'] = 'Grade me';
$string['quiz_update_ngrade_complete'] = 'Update complete';
$string['quiz_update_ngrade_success'] = 'Quiz attempt list successfully updated, currently there is {$a} questions needing grading.';
$string['settings_adminviewall'] = 'Admins View All';
$string['settings_configadminviewall'] = 'Enable to give administrators the rights to see all ungraded work — not just for courses where they have a grader role.';
$string['settings_configenablepre'] = 'Should Grade Me show unrated activity from the "{$a->plugin_name}" module?';
$string['settings_configmaxage'] = 'The maximum age of gradable items, in days, to show. Items older than this will be hidden. Enter 0 for no limit.';
$string['settings_configmaxcourses'] = 'Set the maximum number of ungraded courses to show. Setting this too high may impact performance.';
$string['settings_configshowhidden'] = 'Enable showing items to grade within hidden courses';
$string['settings_enablepre'] = 'Show';
$string['settings_maxage'] = 'Maximum Age';
$string['settings_maxcourses'] = 'Maximum Courses Displayed';
$string['settings_showhidden'] = 'Hidden course items shown';
$string['staledatanotice'] = 'Counts refresh every 15 minutes.';
$string['title'] = 'Grade Me';
$string['ws_get_gradeable_count'] = 'Count and list ungraded items for one module type in a course.';

// Grading Responsiveness section settings.
$string['settings_responsiveness_heading'] = 'Grading Responsiveness';
$string['settings_config_responsiveness_heading'] = 'Settings for the SLA / responsiveness section, the Teacher Dashboard, and the Detailed Report.';
$string['settings_show_responsiveness'] = 'Show responsiveness section';
$string['settings_config_show_responsiveness'] = 'Append the Grading Responsiveness section under the activity list in the block.';
$string['settings_show_school_comparison'] = 'Show school comparison';
$string['settings_config_show_school_comparison'] = 'Include the "School median" and "Top 10%" comparison row inside each group card.';
$string['settings_showhiddenactivities'] = 'Hidden activities shown';
$string['settings_configshowhiddenactivities'] = 'Show items from activities whose course module is hidden (cm.visible = 0). Independent of the hidden-courses setting.';
$string['settings_sla_thresholds'] = 'SLA thresholds (hours)';
$string['settings_config_sla_thresholds'] = 'Comma-separated list of three ascending integers: the upper bound of the Excellent, Good, and Regular buckets. Anything above the third value is Critical.';
$string['settings_sla_goal'] = 'SLA goal (hours)';
$string['settings_config_sla_goal'] = 'Hour mark shown as the dashed goal line on the SLA bar. Usually equal to the Excellent threshold.';
$string['settings_sla_drain_batch'] = 'Drain task batch size';
$string['settings_config_sla_drain_batch'] = 'Maximum number of (course, group, modtype) tuples the drain task recomputes in a single cron run.';
$string['settings_sla_backfill_chunk'] = 'Backfill task chunk size';
$string['settings_config_sla_backfill_chunk'] = 'Maximum number of submission rows the backfill task processes per cron run before yielding.';
$string['settings_report_pagesize'] = 'Report page size';
$string['settings_config_report_pagesize'] = 'Number of submissions per page on the Detailed Report.';
$string['settings_reset_sla'] = 'Reset responsiveness data';
$string['settings_config_reset_sla'] = 'Truncate the SLA ledger, rollups, trend and queue tables and re-queue a full backfill. <a href="{$a}">Reset now</a>.';
$string['reset_sla_done'] = 'Responsiveness data reset; the backfill will run on the next cron tick.';
$string['reset_sla_confirm'] = 'This will truncate the SLA tables and rebuild from {assign_submission}. Continue?';

// Scheduled task names.
$string['task_sla_drain'] = 'Drain the SLA dirty queue (recompute rollups)';
$string['task_sla_trend_recompute'] = 'Recompute the SLA 30-day trend';
$string['task_sla_site_stats'] = 'Recompute site-wide SLA stats';
$string['task_sla_backfill'] = 'Backfill the SLA ledger from scratch';
$string['task_sla_recompute_one'] = 'Recompute one SLA rollup tuple (adhoc, fired after grading)';

// Responsiveness section + card UI.
$string['responsiveness'] = 'Grading Responsiveness';
$string['responsiveness_loading'] = 'Loading responsiveness data…';
$string['responsiveness_no_groups'] = 'You are not in any groups in this course.';
$string['responsiveness_load_failed'] = 'Failed to load responsiveness data. Try again later.';
$string['typical_response'] = 'Typical response';
$string['within_90'] = '90% within';
$string['longest_wait'] = 'Longest wait';
$string['school_median'] = 'School median';
$string['top_10'] = 'Top 10%';
$string['pending'] = 'Pending';
$string['critical'] = 'Critical';
$string['no_rule'] = 'No rule';
$string['rule'] = 'Rule';
$string['bucket_excellent'] = 'Excellent';
$string['bucket_good'] = 'Good';
$string['bucket_regular'] = 'Regular';
$string['bucket_critical'] = 'Critical';
$string['open_dashboard'] = 'Open Teacher Dashboard';
$string['last_30_days'] = 'Last 30 days';
$string['compare_you'] = 'You';
$string['compare_top10'] = 'Top 10%';
$string['activities_open_close'] = 'Activities · open/close rule';
$string['goal_label'] = 'Goal';
$string['ws_get_responsiveness'] = 'Returns the per-group Grading Responsiveness payload for one course.';

// Teacher Dashboard page.
$string['dashboard_greeting'] = 'Hi, {$a->firstname}';
$string['dashboard_subtitle'] = '{$a->pending} submissions waiting · {$a->overgoal} over goal';
$string['dashboard_active_courses'] = 'Active courses';
$string['dashboard_median_wait'] = 'Median wait';
$string['dashboard_critical_total'] = 'Critical';
$string['dashboard_grade_now'] = 'Grade now · prioritised';
$string['dashboard_open_submission'] = 'Open submission';
$string['dashboard_courses_header'] = 'Your courses';
$string['dashboard_col_course'] = 'Course';
$string['dashboard_col_median'] = 'Median';
$string['dashboard_col_trend'] = '30 days';
$string['dashboard_col_sla'] = 'SLA';
$string['dashboard_open_course'] = 'Open';
$string['dashboard_empty'] = 'You are not in any groups across your courses yet.';
$string['dashboard_sort_label'] = 'Sort by';
$string['dashboard_sort_apply'] = 'Apply';
$string['dashboard_sort_critical_first'] = 'Critical first';
$string['dashboard_sort_pending'] = 'Pending count';
$string['dashboard_sort_median'] = 'Median wait';
$string['dashboard_sort_title'] = 'Course title';
$string['dashboard_overdue'] = 'Overdue';
$string['dashboard_closes_today'] = 'Closes today';
$string['dashboard_closes_soon'] = 'Closes soon';

// Detailed Report page.
$string['report_title'] = 'Pending Grading · Detailed Report';
$string['report_breadcrumb_back'] = 'Back to dashboard';
$string['report_filter_all'] = 'All';
$string['report_sort_waiting'] = 'Longest wait';
$string['report_sort_activity'] = 'Activity';
$string['report_sort_student'] = 'Student name';
$string['report_col_student'] = 'Student';
$string['report_col_activity'] = 'Activity';
$string['report_col_submitted'] = 'Submitted';
$string['report_col_waiting'] = 'Waiting';
$string['report_grade_button'] = 'Grade →';
$string['report_empty'] = 'No submissions match the current filters.';
$string['report_user_override'] = 'Override';
$string['report_user_override_tooltip'] = 'This student has a per-user override on this assignment.';

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
 * Detailed Report page for block_grade_me.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$groupid = required_param('group', PARAM_INT);
$filter  = optional_param('filter', \block_grade_me\output\report_page::FILTER_PENDING, PARAM_ALPHA);
$sort    = optional_param('sort',   \block_grade_me\output\report_page::SORT_WAITING, PARAM_ALPHA);
$page    = optional_param('page', 0, PARAM_INT);

require_login();

global $DB;
$group = $DB->get_record('groups', ['id' => $groupid], '*', MUST_EXIST);
$courseid = (int) $group->courseid;
$context = context_course::instance($courseid);

require_capability('block/grade_me:viewresponsiveness', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/grade_me/report.php', [
    'group'  => $groupid,
    'filter' => $filter,
    'sort'   => $sort,
    'page'   => $page,
]));
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('report_title', 'block_grade_me'));
$PAGE->set_heading(get_string('report_title', 'block_grade_me'));

echo $OUTPUT->header();

$page_renderable = new \block_grade_me\output\report_page($groupid, $courseid, $filter, $sort, $page);
$renderer = $PAGE->get_renderer('block_grade_me');
echo $renderer->render_from_template('block_grade_me/report', $page_renderable->export_for_template($renderer));

echo $OUTPUT->footer();

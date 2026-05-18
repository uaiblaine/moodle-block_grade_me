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
 * Teacher Dashboard page for block_grade_me.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();

// Allow access when either:
//  - the user has viewdashboard at system context (managers/admins by archetype), or
//  - the user has viewresponsiveness in at least one course they teach. The
//    system-context archetype grant doesn't propagate to teachers whose roles
//    only live at course context, so check that explicitly here.
$canview = has_capability('block/grade_me:viewdashboard', $context);
if (!$canview) {
    foreach (enrol_get_my_courses() as $coursecandidate) {
        $coursecontext = context_course::instance($coursecandidate->id);
        if (has_capability('block/grade_me:viewresponsiveness', $coursecontext)) {
            $canview = true;
            break;
        }
    }
}
if (!$canview) {
    throw new required_capability_exception($context, 'block/grade_me:viewdashboard', 'nopermissions', '');
}

$sort = optional_param('sort', \block_grade_me\output\dashboard_page::SORT_CRITICAL_FIRST, PARAM_ALPHAEXT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/grade_me/dashboard.php', ['sort' => $sort]));
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('open_dashboard', 'block_grade_me'));
$PAGE->set_heading(get_string('open_dashboard', 'block_grade_me'));

echo $OUTPUT->header();

$page = new \block_grade_me\output\dashboard_page((int) $USER->id, $sort);
$renderer = $PAGE->get_renderer('block_grade_me');
echo $renderer->render_from_template('block_grade_me/dashboard', $page->export_for_template($renderer));

echo $OUTPUT->footer();

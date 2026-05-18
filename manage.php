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
 * Admin manage page for block_grade_me.
 *
 * Currently exposes one action — `reset_sla` — which truncates the SLA ledger,
 * rollup, trend and queue tables and re-queues a full backfill. The first
 * visit shows a confirmation interstitial; the second (with confirm=1)
 * performs the reset and returns to the block settings.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_sesskey();

$action = required_param('action', PARAM_ALPHAEXT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$context = context_system::instance();
$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'blocksettinggrade_me']);

if ($action === 'reset_sla') {
    require_capability('block/grade_me:resetdata', $context);

    if (!$confirm) {
        $PAGE->set_context($context);
        $PAGE->set_url(new moodle_url('/blocks/grade_me/manage.php', [
            'action'  => 'reset_sla',
            'sesskey' => sesskey(),
        ]));
        $PAGE->set_pagelayout('admin');
        $PAGE->set_title(get_string('settings_reset_sla', 'block_grade_me'));
        $PAGE->set_heading(get_string('settings_reset_sla', 'block_grade_me'));

        $continueurl = new moodle_url('/blocks/grade_me/manage.php', [
            'action'  => 'reset_sla',
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]);

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('settings_reset_sla', 'block_grade_me'));
        echo $OUTPUT->confirm(
            get_string('reset_sla_confirm', 'block_grade_me'),
            $continueurl,
            $settingsurl
        );
        echo $OUTPUT->footer();
        exit;
    }

    global $DB;
    $DB->delete_records('block_grade_me_sla_sub');
    $DB->delete_records('block_grade_me_sla_group');
    $DB->delete_records('block_grade_me_sla_trend');
    $DB->delete_records('block_grade_me_sla_site');
    $DB->delete_records('block_grade_me_sla_queue');

    set_config('sla_backfill_cursor', 0, 'block_grade_me');
    set_config('sla_backfill_active', 1, 'block_grade_me');

    \cache_helper::purge_by_definition('block_grade_me', 'responsiveness_payload');
    \cache_helper::purge_by_definition('block_grade_me', 'gradeable_count');

    redirect(
        $settingsurl,
        get_string('reset_sla_done', 'block_grade_me'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

throw new moodle_exception('invalidaction');

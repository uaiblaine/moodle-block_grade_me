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
 * Grade Me Block settings.
 *
 * Storage convention used here:
 *  - Legacy core settings (admin viewall, maxage, enableshowhidden, maxcourses,
 *    per-activity enable<plugin>) use the no-slash form `block_grade_me_<key>`
 *    so they live in {config}. The original plugin reads them via
 *    `$CFG->block_grade_me_<key>` throughout — do not change without auditing
 *    every read.
 *  - New SLA settings use the slash form `block_grade_me/<key>` so they live
 *    in {config_plugins} and reads via `get_config('block_grade_me', '<key>')`
 *    pick them up. Migration is handled in db/upgrade.php.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $adminviewallsetting = new admin_setting_configcheckbox(
        'block_grade_me_enableadminviewall',
        get_string('settings_adminviewall', 'block_grade_me'),
        get_string('settings_configadminviewall', 'block_grade_me'),
        0
    );
    // Toggling viewall changes which courses/groups the block + SLA see;
    // invalidate the cached WS payloads so the next render reflects it.
    $adminviewallsetting->set_updatedcallback('block_grade_me_invalidate_rollups');
    $settings->add($adminviewallsetting);

    $maxcoursessetting = new admin_setting_configtext(
        'block_grade_me_maxcourses',
        get_string('settings_maxcourses', 'block_grade_me'),
        get_string('settings_configmaxcourses', 'block_grade_me'),
        10,
        PARAM_INT
    );
    $maxcoursessetting->set_updatedcallback('block_grade_me_invalidate_rollups');
    $settings->add($maxcoursessetting);

    $maxagesetting = new admin_setting_configtext(
        'block_grade_me_maxage',
        get_string('settings_maxage', 'block_grade_me'),
        get_string('settings_configmaxage', 'block_grade_me'),
        0,
        PARAM_INT
    );
    // Tightening / loosening maxage changes which rows the rollup counts;
    // re-enqueue every (course, group, modtype) so the drain task recomputes
    // them on the next tick, and purge the cached WS payloads.
    $maxagesetting->set_updatedcallback('block_grade_me_invalidate_rollups');
    $settings->add($maxagesetting);

    $showhiddensetting = new admin_setting_configcheckbox(
        'block_grade_me_enableshowhidden',
        get_string('settings_showhidden', 'block_grade_me'),
        get_string('settings_configshowhidden', 'block_grade_me'),
        0
    );
    $showhiddensetting->set_updatedcallback('block_grade_me_invalidate_rollups');
    $settings->add($showhiddensetting);

    $showhiddenactivitiessetting = new admin_setting_configcheckbox(
        'block_grade_me_enableshowhiddenactivities',
        get_string('settings_showhiddenactivities', 'block_grade_me'),
        get_string('settings_configshowhiddenactivities', 'block_grade_me'),
        0
    );
    $showhiddenactivitiessetting->set_updatedcallback('block_grade_me_invalidate_rollups');
    $settings->add($showhiddenactivitiessetting);

    $plugins = get_list_of_plugins('blocks/grade_me/plugins');
    foreach ($plugins as $plugin) {
        if (file_exists($CFG->dirroot . '/blocks/grade_me/plugins/' . $plugin . '/' . $plugin . '_plugin.php')) {
            include_once($CFG->dirroot . '/blocks/grade_me/plugins/' . $plugin . '/' . $plugin . '_plugin.php');
            if (function_exists('block_grade_me_required_capability_' . $plugin)) {
                $requiredcapability = 'block_grade_me_required_capability_' . $plugin;
                $a = $requiredcapability();
                $component = 'mod_' . $plugin;
                if (\core_plugin_manager::instance()->get_plugin_info($component)) {
                    $langshowmod = get_string('settings_enablepre', 'block_grade_me');
                    $langshowmod .= ' ' . get_string('modulenameplural', $component);
                    $langmodname = get_string('modulename', $component);
                    $langshowdesc = get_string('settings_configenablepre', 'block_grade_me', ['plugin_name' => $langmodname]);
                    $settingname = 'block_grade_me_enable' . $plugin;
                    $default = (isset($a[$plugin]) && isset($a[$plugin]['default_on'])) ? $a[$plugin]['default_on'] : false;
                    $settings->add(new admin_setting_configcheckbox($settingname, $langshowmod, $langshowdesc, $default));
                }
            }
        }
    }

    $label = get_string('grade_me_tools', 'block_grade_me');
    $desc = get_string('grade_me_tools_desc', 'block_grade_me', $CFG->wwwroot);
    $settings->add(new admin_setting_heading('grade_me_tools', $label, $desc));

    $settings->add(new admin_setting_heading(
        'block_grade_me_responsiveness_heading',
        get_string('settings_responsiveness_heading', 'block_grade_me'),
        get_string('settings_config_responsiveness_heading', 'block_grade_me')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_grade_me_show_responsiveness',
        get_string('settings_show_responsiveness', 'block_grade_me'),
        get_string('settings_config_show_responsiveness', 'block_grade_me'),
        1
    ));

    $showcomparesetting = new admin_setting_configcheckbox(
        'block_grade_me/show_school_comparison',
        get_string('settings_show_school_comparison', 'block_grade_me'),
        get_string('settings_config_show_school_comparison', 'block_grade_me'),
        1
    );
    $showcomparesetting->set_updatedcallback('block_grade_me_invalidate_rollups');
    $settings->add($showcomparesetting);

    $thresholdsetting = new admin_setting_configtext(
        'block_grade_me/sla_thresholds',
        get_string('settings_sla_thresholds', 'block_grade_me'),
        get_string('settings_config_sla_thresholds', 'block_grade_me'),
        '24,48,120',
        PARAM_TEXT
    );
    // Changing the bucket boundaries changes the critical/overgoal counts in
    // every rollup row — invalidate.
    $thresholdsetting->set_updatedcallback('block_grade_me_invalidate_rollups');
    $settings->add($thresholdsetting);

    $goalsetting = new admin_setting_configtext(
        'block_grade_me/sla_goal',
        get_string('settings_sla_goal', 'block_grade_me'),
        get_string('settings_config_sla_goal', 'block_grade_me'),
        24,
        PARAM_INT
    );
    // The goal affects overgoal counting; invalidate so rollups recompute.
    $goalsetting->set_updatedcallback('block_grade_me_invalidate_rollups');
    $settings->add($goalsetting);

    $drainbatchsetting = new admin_setting_configtext(
        'block_grade_me/sla_drain_batch',
        get_string('settings_sla_drain_batch', 'block_grade_me'),
        get_string('settings_config_sla_drain_batch', 'block_grade_me'),
        200,
        PARAM_INT
    );
    $settings->add($drainbatchsetting);

    $backfillchunksetting = new admin_setting_configtext(
        'block_grade_me/sla_backfill_chunk',
        get_string('settings_sla_backfill_chunk', 'block_grade_me'),
        get_string('settings_config_sla_backfill_chunk', 'block_grade_me'),
        5000,
        PARAM_INT
    );
    $settings->add($backfillchunksetting);

    $reportpagesizesetting = new admin_setting_configtext(
        'block_grade_me/report_pagesize',
        get_string('settings_report_pagesize', 'block_grade_me'),
        get_string('settings_config_report_pagesize', 'block_grade_me'),
        50,
        PARAM_INT
    );
    $settings->add($reportpagesizesetting);

    $resetlink = new moodle_url('/blocks/grade_me/manage.php', [
        'action' => 'reset_sla',
        'sesskey' => sesskey(),
    ]);
    $settings->add(new admin_setting_description(
        'block_grade_me_reset_sla',
        get_string('settings_reset_sla', 'block_grade_me'),
        get_string('settings_config_reset_sla', 'block_grade_me', $resetlink->out(false))
    ));
}

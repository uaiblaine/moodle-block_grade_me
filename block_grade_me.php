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
 * Grade Me block.
 *
 * @package    block_grade_me
 * @copyright  2013 Dakota Duff {@link http://www.remote-learner.net}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Grade Me block definition.
 */
class block_grade_me extends block_base {
    /**
     * Block initialization.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_grade_me', []);
    }

    /**
     * This function does the work to query ungraded assignments according
     * to plugins present in the /grade_me/plugins directory which are
     * gradeable by the current user, and returns the block content to be
     * displayed.
     *
     * @return stdClass The content being rendered for this block
     */
    public function get_content() {
        global $CFG, $USER, $COURSE, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        require_once($CFG->dirroot . '/blocks/grade_me/lib.php');

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (!isloggedin()) {
            return $this->content;
        }

        $enabledplugins = block_grade_me_enabled_plugins();
        $maxcourses = isset($CFG->block_grade_me_maxcourses) ? (int) $CFG->block_grade_me_maxcourses : 10;

        if ($COURSE->id == SITEID) {
            if (is_siteadmin() && !empty($CFG->block_grade_me_enableadminviewall)) {
                $courses = get_courses();
            } else {
                $courses = enrol_get_my_courses();
            }
        } else {
            $courses = [$COURSE->id => $COURSE];
        }

        $skeletoncourses = [];
        $excessmessage = '';
        foreach ($courses as $courseid => $course) {
            if ($courseid == SITEID) {
                continue;
            }
            $context = context_course::instance($courseid);
            $moduletypes = block_grade_me_enumerate_skeleton($courseid, $enabledplugins, $context);
            if (empty($moduletypes)) {
                continue;
            }
            if (count($skeletoncourses) >= $maxcourses) {
                $excessmessage = get_string('excess', 'block_grade_me', ['maxcourses' => $maxcourses]);
                break;
            }
            $skeletoncourses[] = [
                'courseid'    => (int) $courseid,
                'coursename'  => format_string($course->fullname ?? $course->shortname ?? ''),
                'courseurl'   => $CFG->wwwroot . '/course/view.php?id=' . (int) $courseid,
                'moduletypes' => $moduletypes,
            ];
        }

        if (!empty($skeletoncourses)) {
            $payload = [
                'courses'       => $skeletoncourses,
                'maxcourses'    => $maxcourses,
                'excessmessage' => $excessmessage,
            ];
            $this->content->text = $OUTPUT->render_from_template('block_grade_me/block_skeleton', [
                'excessmessage' => $excessmessage,
                // Embed the skeleton payload inside the block markup so the AMD
                // module can self-initialise. js_call_amd() from get_content()
                // can race the page-footer flush in some 4.x layouts, leaving
                // the bootstrap script missing from the page; reading the JSON
                // from the DOM avoids that timing problem entirely.
                'datajson' => json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            ]);
            $this->content->footer =
                '<small class="text-muted d-block block_grade_me-footer">'
                . s(get_string('staledatanotice', 'block_grade_me'))
                . ' <span class="grademe-lastsynced" data-lastsynced="0">'
                . s(get_string('lastsynced_pending', 'block_grade_me'))
                . '</span></small>';
            return $this->content;
        }

        // No gradeables — show the "nothing to grade" message if the user is a grader anywhere.
        $graderroles = [];
        foreach ($enabledplugins as $plugin => $a) {
            foreach (array_keys(get_roles_with_capability($a['capability'])) as $role) {
                $graderroles[$role] = true;
            }
        }
        foreach ($graderroles as $roleid => $value) {
            if (user_has_role_assignment($USER->id, $roleid) || is_siteadmin()) {
                $this->content->text = '<div class="excess">' . get_string('nothing', 'block_grade_me') . '</div>' . "\n";
                break;
            }
        }

        return $this->content;
    }

    /**
     * The Grade Me block should only be available under a course context.
     *
     * @return array The formats which apply to this block
     */
    public function applicable_formats() {
        return ['all' => true];
    }

    /**
     * Required in Moodle 2.4 to load /grade_me/settings.php file
     * @return bool Whether or not to include settings.php
     */
    public function has_config() {
        return true;
    }
}

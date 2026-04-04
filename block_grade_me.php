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
        global $CFG, $USER, $COURSE, $DB, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        require_once($CFG->dirroot . '/blocks/grade_me/lib.php');
        $this->page->requires->jquery();
        $this->page->requires->js('/blocks/grade_me/javascript/grademe.js');

        // Create the content class.
        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (!isloggedin()) {
            return $this->content;
        }

        // Setup arrays.
        $gradeables = [];

        $groups = null;

        $enabledplugins = block_grade_me_enabled_plugins();

        $maxcourses = (isset($CFG->block_grade_me_maxcourses)) ? $CFG->block_grade_me_maxcourses : 10;
        $coursecount = 0;
        $additional = null;

        if ($COURSE->id == SITEID) {
            if (is_siteadmin() && $CFG->block_grade_me_enableadminviewall) {
                $courses = get_courses();
            } else {
                $courses = enrol_get_my_courses();
            }
        } else {
            $courses[$COURSE->id] = $COURSE;
        }

        foreach ($courses as $courseid => $course) {
            unset($params);
            $gradeables = [];
            $context = context_course::instance($courseid);

            // Map each plugin to the userid column it filters on.
            // This is needed because each plugin's query references a different table alias.
            $pluginuseridcolumns = [
                'assign'   => 'asgn_sub.userid',
                'quiz'     => 'bneeds.userid',
                'forum'    => 'fp.userid',
                'data'     => 'dr.userid',
                'glossary' => 'ge.userid',
                'lesson'   => 'la.userid',
            ];

            $params['courseid'] = $courseid;

            foreach ($enabledplugins as $plugin => $a) {
                if (has_capability($a['capability'], $context)) {
                    $useridcol = $pluginuseridcolumns[$plugin] ?? 'userid';
                    [$usersql, $userparams] = \block_grade_me\db_helper::get_gradebook_users_sql(
                        $useridcol,
                        $courseid,
                        $context,
                        $USER->id,
                        $course
                    );

                    $fn = 'block_grade_me_query_' . $plugin;
                    $pluginfn = $fn($usersql, $userparams);
                    if ($pluginfn !== false) {
                        [$sql, $inparams] = $fn($usersql, $userparams);
                        $query = block_grade_me_query_prefix() . $sql . block_grade_me_query_suffix($plugin);
                        $values = array_merge($inparams, $params);
                        $rs = $DB->get_recordset_sql($query, $values);

                        foreach ($rs as $r) {
                            $gradeables = block_grade_me_array($gradeables, $r);
                        }
                    }
                }
            }
            if (count($gradeables) > 0) {
                $coursecount++;
                if ($coursecount > $maxcourses) {
                    $additional = get_string('excess', 'block_grade_me', ['maxcourses' => $maxcourses]);
                    break 1;
                } else {
                    ksort($gradeables);

                    // Batch-load all user records for this course's gradeables
                    // in a single query instead of N+1 individual lookups.
                    $userids = [];
                    foreach ($gradeables as $key => $item) {
                        if ($key === 'meta') {
                            continue;
                        }
                        foreach ($item as $subkey => $submission) {
                            if ($subkey === 'meta') {
                                continue;
                            }
                            if (isset($submission['meta']['userid'])) {
                                $userids[$submission['meta']['userid']] = true;
                            }
                        }
                    }
                    $usercache = [];
                    if (!empty($userids)) {
                        $usercache = $DB->get_records_list('user', 'id', array_keys($userids), '', 'id,firstname,lastname');
                    }

                    $this->content->text .= block_grade_me_tree($gradeables, $usercache);
                }
            }
            unset($gradeables);
        }

        $graderroles = [];
        foreach ($enabledplugins as $plugin => $a) {
            foreach (array_keys(get_roles_with_capability($a['capability'])) as $role) {
                $graderroles[$role] = true;
            }
        }
        $showempty = false;
        foreach ($graderroles as $roleid => $value) {
            if (user_has_role_assignment($USER->id, $roleid) || is_siteadmin()) {
                $showempty = true;
            }
        }

        if (!empty($this->content->text)) {
             // Expand/Collapse button.
             $expand = '<button class="btn btn-sm btn-outline-secondary" type="button" onclick="togglecollapseall();">' .
                get_string('expand', 'block_grade_me') . '</button>';

            $this->content->text = $expand . '<dl>' . $this->content->text . '</dl><div class="excess">' . $additional . '</div>';
        } else if (empty($this->content->text) && $showempty) {
            $this->content->text .= '<div class="excess">' . get_string('nothing', 'block_grade_me') . '</div>' . "\n";
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

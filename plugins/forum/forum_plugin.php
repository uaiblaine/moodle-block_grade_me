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
 * Forum plugin file.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Required capabilities for the forum plugin.
 *
 * @return array Array of required capability information.
 */
function block_grade_me_required_capability_forum() {
    $enabledplugins['forum'] = [
        'capability' => 'mod/forum:rate',
        'default_on' => false,
        'versiondependencies' => 'ANY_VERSION',
        ];
    return $enabledplugins;
}

/**
 * Build SQL query for the forum plugin.
 *
 * @param string $usersql  SQL fragment for filtering users (e.g. "fp.userid IN (SELECT ...)")
 * @param array  $userparams Named parameters for $usersql
 * @return array|false SQL query and parameters, or false when $usersql is empty
 */
function block_grade_me_query_forum(string $usersql, array $userparams) {
    global $USER, $DB;

    if (empty($usersql)) {
        return false;
    }
    $concatid = $DB->sql_concat('fp.id', "'-'", $USER->id);
    $concatitem = $DB->sql_concat('r.itemid', "'-'", 'r.userid');

    $query = ", fp.id submissionid, fp.userid, fp.modified timesubmitted, fd.id as forum_discussion_id
        FROM {forum_posts} fp
        JOIN {forum_discussions} fd ON fd.id = fp.discussion
        JOIN {forum} f ON f.id = fd.forum
   LEFT JOIN {block_grade_me} bgm ON bgm.courseid = f.course AND bgm.iteminstance = f.id
       WHERE $usersql
         AND f.assessed != 0
         AND $concatid NOT IN (
             SELECT $concatitem
               FROM {rating} r
              WHERE r.contextid IN (
                    SELECT cx.id
                      FROM {context} cx
                     WHERE cx.contextlevel = 70
                           AND cx.instanceid = bgm.coursemoduleid
                    )
             )";

    return [$query, $userparams];
}

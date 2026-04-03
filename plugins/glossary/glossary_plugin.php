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

function block_grade_me_required_capability_glossary() {
    $enabledplugins['glossary'] = array(
        'capability' => 'mod/glossary:rate',
        'default_on' => false,
        'versiondependencies' => 'ANY_VERSION'
        );
    return $enabledplugins;
}

/**
 * Build SQL query for the glossary plugin.
 *
 * @param string $usersql  SQL fragment for filtering users (e.g. "ge.userid IN (SELECT ...)")
 * @param array  $userparams Named parameters for $usersql
 * @return array|false SQL query and parameters, or false when $usersql is empty
 */
function block_grade_me_query_glossary(string $usersql, array $userparams) {
    global $USER, $DB;

    if (empty($usersql)) {
        return false;
    }
    $concatid = $DB->sql_concat('ge.id', "'-'", $USER->id);
    $concatitem = $DB->sql_concat('r.itemid', "'-'", 'r.userid');

    $query = ", ge.id submissionid, ge.userid, ge.timemodified timesubmitted
        FROM {glossary_entries} ge
        JOIN {glossary} g ON g.id = ge.glossaryid
   LEFT JOIN {block_grade_me} bgm ON bgm.courseid = g.course AND bgm.iteminstance = ge.glossaryid
       WHERE $usersql
         AND g.assessed >= 1
         AND g.scale <> 0
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

    return array($query, $userparams);
}

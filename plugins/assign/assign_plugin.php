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
 * Grade Me Moodle 2.3+ assign plugin.
 *
 * @package    block_grade_me
 * @copyright  2013 Dakota Duff {@link http://www.remote-learner.net}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @return array Specifics on the capabilities of the assign plugin type
 */
function block_grade_me_required_capability_assign() {
    $enabledplugins['assign'] = [
        'capability' => 'mod/assign:grade',
        'default_on' => true,
        'versiondependencies' => 'ANY_VERSION',
        ];
    return $enabledplugins;
}

/**
 * Build SQL query for the assign plugin.
 *
 * @param string $usersql  SQL fragment for filtering users (e.g. "asgn_sub.userid IN (SELECT ...)")
 * @param array  $userparams Named parameters for $usersql
 * @return array|false SQL query and parameters, or false when $usersql is empty
 */
function block_grade_me_query_assign(string $usersql, array $userparams) {
    if (empty($usersql)) {
        return false;
    }

    $query = ", asgn_sub.id submissionid, asgn_sub.userid, asgn_sub.timemodified timesubmitted,
                asgn_sub.attemptnumber, a.maxattempts
        FROM {assign_submission} asgn_sub
        JOIN {assign} a ON a.id = asgn_sub.assignment
   LEFT JOIN {block_grade_me} bgm ON bgm.courseid = a.course AND bgm.iteminstance = a.id
   LEFT JOIN {assign_grades} ag ON ag.assignment = asgn_sub.assignment AND ag.userid = asgn_sub.userid AND
        asgn_sub.attemptnumber = ag.attemptnumber
       WHERE $usersql AND asgn_sub.status = 'submitted' AND a.grade <> 0
         AND (ag.id IS NULL OR asgn_sub.timemodified >= ag.timemodified)
         AND (a.maxattempts = 1 OR asgn_sub.attemptnumber = (
             SELECT MAX(s2.attemptnumber)
               FROM {assign_submission} s2
              WHERE s2.assignment = asgn_sub.assignment
                AND s2.userid = asgn_sub.userid
         ))";

    return [$query, $userparams];
}

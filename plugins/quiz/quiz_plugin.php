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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/states.php');

require_once($CFG->dirroot . '/question/engine/lib.php');

function block_grade_me_required_capability_quiz() {
    $enabledplugins['quiz'] = [
        'capability' => 'mod/quiz:grade',
        'default_on' => true,
        'versiondependencies' => 'ANY_VERSION',
        ];
    return $enabledplugins;
}

/**
 * Build SQL query for the quiz plugin.
 *
 * @param string $usersql  SQL fragment for filtering users (e.g. "bneeds.userid IN (SELECT ...)")
 * @param array  $userparams Named parameters for $usersql
 * @return array|false SQL query and parameters, or false when $usersql is empty
 */
function block_grade_me_query_quiz(string $usersql, array $userparams) {
    if (empty($usersql)) {
        return false;
    }

    $query = ", qas.id step_id, qza.userid, qza.timemodified timesubmitted, qza.id submissionid, qas.sequencenumber
        FROM {question_attempt_steps} qas
        JOIN {block_grade_me_quiz_ngrade} bneeds ON bneeds.questionattemptstepid = qas.id
                                                    AND $usersql
        JOIN {quiz_attempts} qza ON qas.id = bneeds.questionattemptstepid
        JOIN {question_attempts} qna ON qna.questionusageid = qza.uniqueid
                                        AND qas.questionattemptid = qna.id
        JOIN {block_grade_me} bgm ON bgm.iteminstance = qza.quiz
                                     AND bgm.itemmodule = 'quiz'
       WHERE qas.state = '" . question_state::$needsgrading . "'";
    return [$query, $userparams];
}

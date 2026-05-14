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
 * External web service: count ungraded items for one (course, moduletype).
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\external;

defined('MOODLE_INTERNAL') || die();

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;

global $CFG;
require_once($CFG->dirroot . '/blocks/grade_me/lib.php');

/**
 * Get count + per-submission details of gradeables for a single module type
 * inside a single course, scoped to what the current user is allowed to grade.
 */
class get_gradeable_count extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
            'moduletype' => new external_value(PARAM_ALPHANUMEXT,
                'Module type (assign|quiz|forum|glossary|data|lesson)'),
        ]);
    }

    /**
     * Run the query and return counts + submissions for the caller.
     *
     * @param int $courseid
     * @param string $moduletype
     * @return array
     */
    public static function execute(int $courseid, string $moduletype): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'   => $courseid,
            'moduletype' => $moduletype,
        ]);
        $courseid = $params['courseid'];
        $moduletype = $params['moduletype'];

        $context = context_course::instance($courseid);
        self::validate_context($context);

        $enabled = block_grade_me_enabled_plugins();
        if (!isset($enabled[$moduletype]) || empty($enabled[$moduletype]['capability'])) {
            throw new invalid_parameter_exception('moduletype');
        }
        require_capability($enabled[$moduletype]['capability'], $context);

        // Session cache: per-user, 15-minute TTL (see db/caches.php).
        $cache = \cache::make('block_grade_me', 'gradeable_count');
        $cachekey = $courseid . '_' . $moduletype;
        $hit = $cache->get($cachekey);
        if (is_array($hit) && isset($hit['payload'], $hit['lastsynced'])) {
            $payload = $hit['payload'];
            $payload['lastsynced'] = (int) $hit['lastsynced'];
            return $payload;
        }

        $course = get_course($courseid);

        $useridcolumns = [
            'assign'   => 'asgn_sub.userid',
            'quiz'     => 'bneeds.userid',
            'forum'    => 'fp.userid',
            'data'     => 'dr.userid',
            'glossary' => 'ge.userid',
            'lesson'   => 'la.userid',
        ];
        [$usersql, $userparams] = \block_grade_me\db_helper::get_gradebook_users_sql(
            $useridcolumns[$moduletype] ?? 'userid',
            $courseid,
            $context,
            $USER->id,
            $course
        );

        $now = time();
        $empty = [
            'success'     => true,
            'courseid'    => $courseid,
            'moduletype'  => $moduletype,
            'count'       => 0,
            'submissions' => [],
            'lastsynced'  => $now,
        ];

        $fn = 'block_grade_me_query_' . $moduletype;
        if (!function_exists($fn)) {
            $cache->set($cachekey, ['payload' => $empty, 'lastsynced' => $now]);
            return $empty;
        }
        $pluginfn = $fn($usersql, $userparams);
        if ($pluginfn === false) {
            $cache->set($cachekey, ['payload' => $empty, 'lastsynced' => $now]);
            return $empty;
        }
        [$sql, $inparams] = $pluginfn;

        $query = block_grade_me_query_prefix() . $sql . block_grade_me_query_suffix($moduletype);
        $values = array_merge($inparams, ['courseid' => $courseid]);

        $rows = [];
        $userids = [];
        $rs = $DB->get_recordset_sql($query, $values);
        foreach ($rs as $r) {
            $rows[] = $r;
            $userids[$r->userid] = true;
        }
        $rs->close();

        $users = !empty($userids)
            ? $DB->get_records_list('user', 'id', array_keys($userids), '', 'id, firstname, lastname')
            : [];

        $submissions = [];
        foreach ($rows as $r) {
            $u = $users[$r->userid] ?? null;
            $submissions[] = [
                'submissionid'   => (int) $r->submissionid,
                'userid'         => (int) $r->userid,
                'userfullname'   => $u ? fullname($u) : '',
                'timesubmitted'  => (int) $r->timesubmitted,
                'coursemoduleid' => (int) $r->coursemoduleid,
                'itemname'       => (string) $r->itemname,
                'gradelink'      => block_grade_me_build_gradelink(
                    $moduletype,
                    (int) $r->coursemoduleid,
                    (int) $r->userid,
                    (int) $r->submissionid,
                    isset($r->forum_discussion_id) ? (int) $r->forum_discussion_id : null
                ),
            ];
        }

        $payload = [
            'success'     => true,
            'courseid'    => $courseid,
            'moduletype'  => $moduletype,
            'count'       => count($submissions),
            'submissions' => $submissions,
            'lastsynced'  => $now,
        ];
        $cache->set($cachekey, ['payload' => $payload, 'lastsynced' => $now]);
        return $payload;
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'     => new external_value(PARAM_BOOL, 'Always true on a non-exception response'),
            'courseid'    => new external_value(PARAM_INT, 'Course ID echoed back from request'),
            'moduletype'  => new external_value(PARAM_ALPHANUMEXT, 'Module type echoed back from request'),
            'count'       => new external_value(PARAM_INT, 'Number of ungraded submissions'),
            'submissions' => new external_multiple_structure(
                new external_single_structure([
                    'submissionid'   => new external_value(PARAM_INT, 'Submission/attempt id'),
                    'userid'         => new external_value(PARAM_INT, 'Submitter user id'),
                    'userfullname'   => new external_value(PARAM_RAW, 'Submitter full name'),
                    'timesubmitted'  => new external_value(PARAM_INT, 'Unix timestamp of submission'),
                    'coursemoduleid' => new external_value(PARAM_INT, 'Course module id'),
                    'itemname'       => new external_value(PARAM_RAW, 'Activity name'),
                    'gradelink'      => new external_value(PARAM_URL, 'URL opening the grading screen for this submission'),
                ])
            ),
            'lastsynced'  => new external_value(PARAM_INT, 'Unix timestamp of when these counts were computed'),
        ]);
    }
}

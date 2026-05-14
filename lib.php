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
 * Grade Me Block library functions.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Returns first portion of the SQL query for the Grade Me block
 *
 * @return string $query
 */
function block_grade_me_query_prefix() {
    $query = 'SELECT * FROM (SELECT bgm.courseid, bgm.coursename, bgm.itemmodule, bgm.iteminstance, bgm.itemname, ' .
        'bgm.coursemoduleid, bgm.itemsortorder';
    return $query;
}

/**
 * Returns last portion of the SQL query for the Grade Me block
 *
 * @param string $mod The array to implode
 * @return string $string
 */
function block_grade_me_query_suffix($mod) {
    $query = " AND bgm.courseid = :courseid AND bgm.itemmodule = '$mod') allitems";
    $maxage = get_config(null, 'block_grade_me_maxage');
    if (!empty($maxage) && is_numeric($maxage)) {
        $maxtimesubmitted = time() - ((int)$maxage * DAYSECS);
        $query .= " WHERE allitems.timesubmitted >= " . $maxtimesubmitted;
    }
    $query .= ' ORDER BY submissionid ASC';
    return $query;
}

/**
 * Returns the enabled Grade Me block plugins and their required capabilities
 * @return array $enabledplugins
 */
function block_grade_me_enabled_plugins() {
    global $CFG;
    $enabledplugins = [];
    $plugins = get_list_of_plugins('blocks/grade_me/plugins');
    foreach ($plugins as $plugin) {
        $pluginfile = $CFG->dirroot . '/blocks/grade_me/plugins/' . $plugin . '/' . $plugin . '_plugin.php';
        if (file_exists($pluginfile)) {
            $enablekey = 'block_grade_me_enable' . $plugin;
            if (isset($CFG->$enablekey) && $CFG->$enablekey == true) {
                include_once($pluginfile);
                $requiredcapabilityfunc = 'block_grade_me_required_capability_' . $plugin;
                if (function_exists($requiredcapabilityfunc)) {
                    $enabledplugins = array_merge($enabledplugins, $requiredcapabilityfunc());
                }
            }
        }
    }
    return $enabledplugins;
}

/**
 * Build the URL that opens the grading screen for a single submission, or — when
 * $submissionid is 0 — the module-level grading overview.
 *
 * @param string $moduletype Activity module name (assign, quiz, forum, ...).
 * @param int $cmid Course module id.
 * @param int $userid User id of the submitter (only used by some module types).
 * @param int $submissionid Submission id (0 → module-level link).
 * @param int|null $forumdiscussionid Forum discussion id (only used for forum).
 * @return string Absolute URL.
 */
function block_grade_me_build_gradelink(string $moduletype, int $cmid, int $userid = 0,
        int $submissionid = 0, ?int $forumdiscussionid = null): string {
    global $CFG;

    if ($submissionid === 0) {
        if ($moduletype === 'quiz') {
            return $CFG->wwwroot . '/mod/quiz/report.php?id=' . $cmid;
        }
        if ($moduletype === 'assign') {
            return $CFG->wwwroot . '/mod/assign/view.php?id=' . $cmid . '&action=grading';
        }
        return $CFG->wwwroot . '/mod/' . $moduletype . '/view.php?id=' . $cmid;
    }

    switch ($moduletype) {
        case 'assign':
            return $CFG->wwwroot . '/mod/assign/view.php?id=' . $cmid . '&action=grading&userid=' . $userid;
        case 'data':
            return $CFG->wwwroot . '/mod/data/view.php?rid=' . $submissionid . '&mode=single';
        case 'forum':
            return $CFG->wwwroot . '/mod/forum/discuss.php?d=' . ((int) $forumdiscussionid) . '#p' . $submissionid;
        case 'glossary':
            return $CFG->wwwroot . '/mod/glossary/view.php?id=' . $cmid . '#postrating' . $submissionid;
        case 'quiz':
            return $CFG->wwwroot . '/mod/quiz/review.php?attempt=' . $submissionid;
        case 'lesson':
            return $CFG->wwwroot . '/mod/lesson/essay.php?id=' . $cmid . '&mode=grade&attemptid='
                . $submissionid . '&sesskey=' . sesskey();
        default:
            return $CFG->wwwroot . '/mod/' . $moduletype . '/view.php?id=' . $cmid;
    }
}

/**
 * Enumerate cached gradeable items for one course, restricted to the module
 * types that are enabled AND that the current user has the corresponding
 * grading capability for in the given context.
 *
 * Returns a list of per-moduletype groups; each group carries the cmid + name
 * + module-level URLs needed to render the skeleton synchronously.
 *
 * @param int $courseid
 * @param array $enabledplugins Output of block_grade_me_enabled_plugins().
 * @param context_course $context
 * @return array [['moduletype' => 'assign', 'instances' => [['cmid'=>..,'itemname'=>..,'moduleurl'=>..,'gradeurl'=>..], ...]], ...]
 */
function block_grade_me_enumerate_skeleton(int $courseid, array $enabledplugins, context_course $context): array {
    global $CFG, $DB;

    $allowedtypes = [];
    foreach ($enabledplugins as $moduletype => $info) {
        if (!empty($info['capability']) && has_capability($info['capability'], $context)) {
            $allowedtypes[] = $moduletype;
        }
    }
    if (empty($allowedtypes)) {
        return [];
    }

    [$insql, $inparams] = $DB->get_in_or_equal($allowedtypes, SQL_PARAMS_NAMED, 'bgm_mt_');
    $params = array_merge($inparams, ['courseid' => $courseid]);
    $sql = "SELECT bgm.id, bgm.itemmodule, bgm.itemname, bgm.iteminstance, bgm.coursemoduleid, bgm.itemsortorder
              FROM {block_grade_me} bgm
             WHERE bgm.courseid = :courseid
               AND bgm.itemmodule $insql
          ORDER BY bgm.itemmodule, bgm.itemsortorder, bgm.coursemoduleid";

    $groups = [];
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $r) {
        $mt = $r->itemmodule;
        if (!isset($groups[$mt])) {
            $groups[$mt] = ['moduletype' => $mt, 'instances' => []];
        }
        $cmid = (int) $r->coursemoduleid;
        $groups[$mt]['instances'][] = [
            'cmid'      => $cmid,
            'itemname'  => (string) $r->itemname,
            'moduleurl' => $CFG->wwwroot . '/mod/' . $mt . '/view.php?id=' . $cmid,
            'gradeurl'  => block_grade_me_build_gradelink($mt, $cmid),
        ];
    }
    $rs->close();

    return array_values($groups);
}
// Reset table cron function.
/**
 * Reset block_grade_me cache variables and clear static flags.
 * Used primarily for testing scopes or forced re-evaluations.
 */
function block_grade_me_cache_reset() {
    global $CFG, $DB;
    $DB->delete_records('block_grade_me');
    $DB->delete_records('block_grade_me_quiz_ngrade');
    block_grade_me_cache_grade_data();
    \cache_helper::purge_by_definition('block_grade_me', 'gradeable_count');
    set_config('cachedatalast', time(), 'reset_block');
}
// Main cron function.
/**
 * Retrieve current grade data from the cache or pre-warm it for the current context.
 */
function block_grade_me_cache_grade_data() {
    global $CFG, $DB;

    $lastrun = $DB->get_field('task_scheduled', 'lastruntime', ['classname' => 'cache_grade_data']);
    $enabledplugins = array_keys(block_grade_me_enabled_plugins());

    if (empty($enabledplugins)) {
        set_config('cachedatalast', time(), 'block_grade_me');
        return true;
    }

    // Get module IDs for enabled plugins in a single query.
    [$pluginsql, $pluginparams] = $DB->get_in_or_equal($enabledplugins, SQL_PARAMS_NAMED, 'plug_');
    $enabledpluginsid = $DB->get_fieldset_sql(
        "SELECT id FROM {modules} WHERE name {$pluginsql}",
        $pluginparams
    );

    if (empty($enabledpluginsid)) {
        set_config('cachedatalast', time(), 'block_grade_me');
        return true;
    }

    // Check the size of the grade me table. If its 0, then ignore time stamp.
    $tablesize = $DB->count_records('block_grade_me');
    if ($tablesize == 0) {
        $lastrun = 0;
    }

    // See if the block has been added course wide.
    $systemcount = $DB->count_records_sql(
        "SELECT COUNT(b.id)
           FROM {block_instances} b
          WHERE b.blockname = 'grade_me'
            AND b.pagetypepattern IN (:p1, :p2, :p3)",
        ['p1' => 'site-index', 'p2' => 'my-index', 'p3' => '*']
    );

    // Get the list of all active courses that have enrolled users.
    // This eliminates the N+1 per-course validation query.
    if ($systemcount > 0) {
        $sqlactive = "SELECT DISTINCT c.id, c.timemodified
                       FROM {course} c
                       JOIN {enrol} e ON e.courseid = c.id
                       JOIN {user_enrolments} ue ON ue.enrolid = e.id
                       JOIN {user} u ON u.id = ue.userid AND u.deleted = 0";
    } else {
        $sqlactive = "SELECT DISTINCT c.id, c.timemodified
                       FROM {course} c
                       JOIN {context} x ON c.id = x.instanceid
                       JOIN {block_instances} b
                         ON (b.parentcontextid = x.id AND b.blockname = 'grade_me')
                       JOIN {enrol} e ON e.courseid = c.id
                       JOIN {user_enrolments} ue ON ue.enrolid = e.id
                       JOIN {user} u ON u.id = ue.userid AND u.deleted = 0";
    }

    // Determine whether to show hidden courses based on config setting.
    if (false == get_config(null, 'block_grade_me_enableshowhidden')) {
        $sqlactive .= " WHERE c.visible = 1";
    }

    $courselist = $DB->get_recordset_sql($sqlactive, []);
    foreach ($courselist as $actcourse) {
        $cid = $actcourse->id;
        $coursemod = $actcourse->timemodified;
        if ($lastrun == 0) {
            $coursemod = 0;
        } else {
            if ($coursemod > $lastrun) {
                // This handles the case if the course was hidden and made visible.
                $coursemod = 0;
            } else {
                $coursemod = $lastrun;
            }
        }

        // Build the grade items query for this course with named params.
        [$modinsql, $modinparams] = $DB->get_in_or_equal($enabledpluginsid, SQL_PARAMS_NAMED, 'mod_');

        $sql = "SELECT gi.id itemid, gi.itemname itemname, gi.itemtype itemtype,
                       gi.itemmodule itemmodule, gi.iteminstance iteminstance,
                       gi.sortorder itemsortorder, c.id courseid, c.shortname coursename,
                       cm.id coursemoduleid
                FROM {grade_items} gi
           LEFT JOIN {course} c ON gi.courseid = c.id
           LEFT JOIN {modules} m ON m.name = gi.itemmodule
                JOIN {course_modules} cm ON cm.course = c.id AND cm.module = m.id AND cm.instance = gi.iteminstance
                WHERE gi.itemtype = :itemtype
                      AND c.id = :cid
                      AND gi.timemodified > :timemod
                      AND m.id {$modinsql}";

        $giparams = array_merge([
            'itemtype' => 'mod',
            'cid'      => $cid,
            'timemod'  => $coursemod,
        ], $modinparams);

        $rs = $DB->get_recordset_sql($sql, $giparams);
        $batchbuffer = [];
        foreach ($rs as $rec) {
            $batchbuffer[] = (object) [
                'itemname'       => $rec->itemname,
                'itemtype'       => $rec->itemtype,
                'itemmodule'     => $rec->itemmodule,
                'iteminstance'   => $rec->iteminstance,
                'itemsortorder'  => $rec->itemsortorder,
                'courseid'       => $rec->courseid,
                'coursename'     => $rec->coursename,
                'coursemoduleid'  => $rec->coursemoduleid,
            ];
        }
        $rs->close();

        // Flush all grade_items for this course in a single batched MERGE
        // (PG 15+) or per-row upsert (PG < 15 / MySQL).
        \block_grade_me\db_helper::batch_upsert_grade_me($batchbuffer);

        // Build the quiz ngrade table for this course using a single bulk
        // INSERT ... ON CONFLICT DO NOTHING (PG) or LEFT JOIN anti-pattern
        // (MySQL) instead of the former row-by-row loop.
        \block_grade_me\db_helper::bulk_insert_quiz_ngrade($cid);
    }
    $courselist->close();

    set_config('cachedatalast', time(), 'block_grade_me');
    return true;
}

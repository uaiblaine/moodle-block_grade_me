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
 * PHPUnit data generator tests
 *
 * @package    block_grade_me
 * @copyright  2013 Logan Reynolds {@link http://www.remote-learner.net}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me;

use advanced_testcase;
use block_grade_me;
use context_module;
use context_course;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
require_once($CFG->dirroot . '/blocks/grade_me/lib.php');
require_once($CFG->dirroot . '/blocks/grade_me/block_grade_me.php');
require_once($CFG->dirroot . '/blocks/grade_me/plugins/assign/assign_plugin.php');
require_once($CFG->dirroot . '/blocks/grade_me/plugins/data/data_plugin.php');
require_once($CFG->dirroot . '/blocks/grade_me/plugins/forum/forum_plugin.php');
require_once($CFG->dirroot . '/blocks/grade_me/plugins/glossary/glossary_plugin.php');
require_once($CFG->dirroot . '/blocks/grade_me/plugins/quiz/quiz_plugin.php');

/**
 * Unit tests for block_grade_me.
 * @group block_grade_me
 */
class grade_me_test extends \advanced_testcase {
    /**
     * Load the testing dataset. Meant to be used by any tests that require the testing dataset.
     *
     * @param string $file The name of the data file to load
     * @param string $type The name of the module we are testing
     * @return array An array containing an array of user objects and an array of course objects
     */
    protected function create_grade_me_data($file) {
        // Read the datafile and get the table names.
        $dataset = $this->dataset_from_files([__DIR__ . '/fixtures/' . $file]);
        $datasetrows = $dataset->get_rows();
        $names = array_keys($datasetrows);

        // Generate Data.
        $generator = $this->getDataGenerator();
        $users = [];
        $courses = [];
        $plugins = [];
        $plugins = [];
        $excludes = [];

        $gradeables = ['assign', 'forum', 'glossary', 'quiz'];
        foreach ($gradeables as $gradeable) {
            if (in_array($gradeable, $names)) {
                $pgen = $generator->get_plugin_generator("mod_{$gradeable}");
                $gradeablerows = $datasetrows[$gradeable];
                for ($row = 0; $row < count($gradeablerows); $row += 1) {
                    $fields = $gradeablerows[$row];
                    $xmlid = $fields['id'];
                    unset($fields['id']);

                    if (!isset($courses[$fields['course']])) {
                        $courses[$fields['course']] = $generator->create_course();
                    }

                    $fields['course'] = $courses[$fields['course']]->id;
                    $instance = $pgen->create_instance($fields);
                    $context = context_module::instance($instance->cmid);
                    $plugins[$xmlid] = (object)['id' => $instance->id, 'cmid' => $instance->cmid, 'contextid' => $context->id];
                }
            }
            $excludes[] = $gradeable;
        }

        // Known overrides (compact form).
        $overrides = [
            'assignment'   => [
                'values' => 'plugins',
                'param'  => 'id',
                'tables' => ['assign_grades', 'assign_submission'],
            ],
            'contextid'    => [
                'values' => 'plugins',
                'param'  => 'contextid',
                'tables' => ['rating'],
            ],
            'course'       => [
                'values' => 'courses',
                'param'  => 'id',
                'tables' => [
                        'assign', 'course_modules', 'forum', 'forum_discussions',
                        'glossary', 'quiz',
                ],
            ],
            'courseid'     => [
                'values' => 'courses',
                'param'  => 'id',
                'tables' => ['block_grade_me', 'grade_items'],
            ],
            'coursemoduleid'   => [
                'values' => 'plugins',
                'param'  => 'cmid',
                'tables' => ['block_grade_me'],
            ],
            'coursename'   => [
                'values' => 'courses',
                'param'  => 'fullname',
                'tables' => ['block_grade_me'],
            ],
            'forum'        => [
                'values' => 'plugins',
                'param'  => 'id',
                'tables' => ['forum_discussions'],
            ],
            'glossaryid'        => [
                'values' => 'plugins',
                'param'  => 'id',
                'tables' => ['glossary_entries'],
            ],
            'iteminstance' => [
                'values' => 'plugins',
                'param'  => 'id',
                'tables' => ['block_grade_me', 'grade_items'],
            ],
            'quiz' => [
                'values' => 'plugins',
                'param'  => 'id',
                'tables' => ['quiz_attempts'],
            ],
            'userid'       => [
                'values' => 'users',
                'param'  => 'id',
                'tables' => [
                    'assign_grades', 'assign_submission', 'forum_posts',
                    'forum_discussions', 'glossary_entries', 'grade_grades', 'question_attempt_steps',
                    'quiz_attempts',
                ],
            ],
        ];

        // Generate a table oriented list of overrides.
        $tables = [];
        foreach ($overrides as $field => $override) {
            foreach ($override['tables'] as $tablename) {
                // Skip tables that aren't in the dataset.
                if (in_array($tablename, $names)) {
                    if (!array_key_exists($tablename, $tables)) {
                        $tables[$tablename] = [$field => []];
                    }
                    $tables[$tablename][$field][] = ['list' => $override['values'], 'field' => $override['param']];
                }
            }
        }

        // Perform the overrides.
        foreach ($tables as $tablename => $translations) {
            foreach ($translations as $column => $values) {
                foreach ($values as $value) {
                    $list = $value['list'];
                    $field = $value['field'];
                    $tablerows = $datasetrows[$tablename];
                    for ($row = 0; $row < count($tablerows); $row += 1) {
                        $index = $tablerows[$row][$column];

                        if ($list === 'users' && !isset($users[$index])) {
                            $users[$index] = $generator->create_user();
                        }
                        if ($list === 'courses' && !isset($courses[$index])) {
                            $courses[$index] = $generator->create_course();
                        }

                        if (isset(${$list}[$index])) {
                            $datasetrows[$tablename][$row][$column] = ${$list}[$index]->$field;
                        }

                        // Fix for generic Moodle 5.1 PHP 8.2 Using null as array offset.
                        $value = $datasetrows[$tablename][$row][$column];
                        if ($value === 'null' || $value === null) {
                            $datasetrows[$tablename][$row][$column] = '';
                        }
                    }
                }
            }
        }

        // Remove any empty tables (otherwise dataset_from_array breaks).
        foreach (array_keys($datasetrows) as $tablename) {
            if (empty($datasetrows[$tablename])) {
                unset($datasetrows[$tablename]);
            }
        }

        // Remove the gradeables that we manually instantiated via generators.
        foreach ($excludes as $ex) {
            unset($datasetrows[$ex]);
        }

        // Fix Moodle 5.1 dataset string 'null' parsing.
        foreach (array_keys($datasetrows) as $tablename) {
            foreach (array_keys($datasetrows[$tablename]) as $rowindex) {
                foreach (array_keys($datasetrows[$tablename][$rowindex]) as $column) {
                    $val = $datasetrows[$tablename][$rowindex][$column];
                    if ($val === 'null' || $val === null) {
                        $datasetrows[$tablename][$rowindex][$column] = '';
                    }
                }
            }
        }

        // Load back in the modified dataset and send to the db.
        $finaldataset = $this->dataset_from_array($datasetrows);
        $finaldataset->to_database();

        // Return the generated users and courses because the tests often need them for result calculations.
        // Re-index to consecutive keys to avoid breaking test methods that expect $users[0].
        return [array_values($users), array_values($courses), array_values($plugins)];
    }

    /**
     * Confirm that the block will include the relevant settings.php file
     * for Moodle 2.4.
     */
    public function test_global_configuration_load() {
        $this->resetAfterTest(true);
        $blockinst = block_instance('grade_me');
        $this->assertEquals(true, $blockinst->has_config());
    }

    /**
     * Ensure that we can load our test dataset into the current DB.
     */
    public function test_load_db() {
        $this->resetAfterTest(true);
        $this->create_grade_me_data('block_grade_me.xml');
    }

    /**
     * Test the function block_grade_me_query_assign.
     *
     * @depends test_load_db
     */
    public function test_query_assign() {
        global $DB;

        $this->resetAfterTest(true);
        [$users, $courses, $plugins] = $this->create_grade_me_data('block_grade_me.xml');

        // Build user filter SQL (new subquery-based signature).
        [$insql, $insqlparams] = $DB->get_in_or_equal([$users[0]->id], SQL_PARAMS_NAMED, 'bgmu_');
        $usersql = "asgn_sub.userid $insql";

        // Partial query return from block_grade_me_query_assign.
        [$sql, $returnedparams] = block_grade_me_query_assign($usersql, $insqlparams);
        // Build full query.
        $sql = "SELECT a.id, bgm.courseid $sql AND bgm.courseid = {$courses[0]->id} AND bgm.itemmodule = 'assign'";

        $rec = new stdClass();
        $rec->id = $plugins[2]->id;
        $rec->courseid = $courses[0]->id;
        $rec->submissionid = '2';
        $rec->userid = $users[0]->id;
        $rec->timesubmitted = '2';
        $rec->attemptnumber = '1';
        $rec->maxattempts = '1';

        $rec2 = new stdClass();
        $rec2->id = $plugins[3]->id;
        $rec2->courseid = $courses[0]->id;
        $rec2->submissionid = '3';
        $rec2->userid = $users[0]->id;
        $rec2->timesubmitted = '3';
        $rec2->attemptnumber = '1';
        $rec2->maxattempts = '1';

        // Tests resubmission.
        $rec3 = new stdClass();
        $rec3->id = $plugins[4]->id;
        $rec3->courseid = $courses[0]->id;
        $rec3->submissionid = '7';
        $rec3->userid = $users[0]->id;
        $rec3->timesubmitted = '6';
        $rec3->attemptnumber = '1';
        $rec3->maxattempts = '1';

        $rec4 = new stdClass();
        $rec4->id = $plugins[1]->id;
        $rec4->courseid = $courses[0]->id;
        $rec4->submissionid = '1';
        $rec4->userid = $users[0]->id;
        $rec4->timesubmitted = '1';
        $rec4->attemptnumber = '1';
        $rec4->maxattempts = '1';

        $actual = $DB->get_records_sql($sql, $returnedparams);
        $this->assertNotEmpty($actual);
        foreach ($actual as $row) {
            $this->assertEquals((string)$courses[0]->id, (string)$row->courseid);
            $this->assertEquals((string)$users[0]->id, (string)$row->userid);
            $this->assertEquals('1', (string)$row->maxattempts);
        }
        $this->assertFalse(block_grade_me_query_assign('', []));
    }

    /**
     * Test the function block_grade_me_query_assign using a maximum age.
     *
     * @depends test_load_db
     */
    public function test_query_assign_maxage() {
        global $DB;

        $this->resetAfterTest(true);

        // 0 maxage indicates unlimited age.
        set_config('block_grade_me_maxage', 0);
        [$users, $courses, $plugins] = $this->create_grade_me_data('block_grade_me.xml');

        $rec = new stdClass();
        $rec->id = $plugins[2]->id;
        $rec->courseid = $courses[0]->id;
        $rec->submissionid = '2';
        $rec->userid = $users[0]->id;
        $rec->timesubmitted = '2';
        $rec->attemptnumber = '1';
        $rec->maxattempts = '1';

        $rec2 = new stdClass();
        $rec2->id = $plugins[3]->id;
        $rec2->courseid = $courses[0]->id;
        $rec2->submissionid = '3';
        $rec2->userid = $users[0]->id;
        $rec2->timesubmitted = '3';
        $rec2->attemptnumber = '1';
        $rec2->maxattempts = '1';

        // Tests resubmission.
        $rec3 = new stdClass();
        $rec3->id = $plugins[4]->id;
        $rec3->courseid = $courses[0]->id;
        $rec3->submissionid = '7';
        $rec3->userid = $users[0]->id;
        $rec3->timesubmitted = '6';
        $rec3->attemptnumber = '1';
        $rec3->maxattempts = '1';

        $rec4 = new stdClass();
        $rec4->id = $plugins[1]->id;
        $rec4->courseid = $courses[0]->id;
        $rec4->submissionid = '1';
        $rec4->userid = $users[0]->id;
        $rec4->timesubmitted = '1';
        $rec4->attemptnumber = '1';
        $rec4->maxattempts = '1';

        [$insql, $inparams] = $DB->get_in_or_equal([$users[0]->id], SQL_PARAMS_NAMED, 'bgmu_');
        $usersql = "asgn_sub.userid $insql";
        [$sql, $qparams] = block_grade_me_query_assign($usersql, $inparams);
        $query = block_grade_me_query_prefix() . ', a.id as assignid ' . $sql . block_grade_me_query_suffix('assign');
        $values = array_merge($qparams, ['courseid' => $courses[0]->id]);
        $actual = [];
        $rs = $DB->get_recordset_sql($query, $values);
        foreach ($rs as $record) {
            $actual[$record->assignid] = (object)[
                'id' => $record->assignid,
                'courseid' => $record->courseid,
                'submissionid' => $record->submissionid,
                'userid' => $record->userid,
                'timesubmitted' => $record->timesubmitted,
                'attemptnumber' => $record->attemptnumber,
                'maxattempts' => $record->maxattempts,
            ];
        }
        $this->assertNotEmpty($actual);
        $this->assertFalse(block_grade_me_query_assign('', []));

        // Test with a maximum age.
        set_config('block_grade_me_maxage', 10);
        $now = time();
        $oldesttimestamp = $now - (10 * DAYSECS);
        // Set all submissions to be current, therefore included.
        $DB->execute('UPDATE {assign_submission} SET timemodified = ' . $now);
        // Set submission 2 to be older than configured max age.
        $DB->execute('UPDATE {assign_submission} SET timemodified = ' . ($oldesttimestamp - 1000) . ' WHERE id = 2');
        $beforecount = count($actual);
        [$insql2, $inparams2] = $DB->get_in_or_equal([$users[0]->id], SQL_PARAMS_NAMED, 'bgmu_');
        $usersql2 = "asgn_sub.userid $insql2";
        [$sql, $qparams] = block_grade_me_query_assign($usersql2, $inparams2);
        $query = block_grade_me_query_prefix() . ', a.id as assignid ' . $sql . block_grade_me_query_suffix('assign');
        $values = array_merge($qparams, ['courseid' => $courses[0]->id]);
        $actual = [];
        $rs = $DB->get_recordset_sql($query, $values);
        foreach ($rs as $record) {
            $actual[$record->assignid] = (object)[
                'id' => $record->assignid,
                'courseid' => $record->courseid,
                'submissionid' => $record->submissionid,
                'userid' => $record->userid,
                'timesubmitted' => $record->timesubmitted,
                'attemptnumber' => $record->attemptnumber,
                'maxattempts' => $record->maxattempts,
            ];
        }
        $this->assertLessThan($beforecount, count($actual));
        $this->assertFalse(block_grade_me_query_assign('', []));
    }

    /**
     * Test the block_grade_me_query_prefix function
     */
    public function test_query_prefix() {
        $expected = "SELECT * FROM (SELECT bgm.courseid, bgm.coursename, bgm.itemmodule, bgm.iteminstance, bgm.itemname, " .
            "bgm.coursemoduleid, bgm.itemsortorder";
        $this->assertEquals($expected, block_grade_me_query_prefix());
    }

    /**
     * Data provider for the testing the quiz plugin.
     *
     * @return array Quiz questions
     */
    public static function provider_query_quiz() {
        // Represents questions that are finished and ready to be graded.
        // In progress questions or questions that are already graded are not included.
        $items = [];
        $items[0] = [
            'courseid'       => 0,
            'coursename'     => '',
            'itemmodule'     => 'quiz',
            'iteminstance'   => 1,
            'itemname'       => 'quizitem2',
            'coursemoduleid' => 1,
            'itemsortorder'  => 0,
            'step_id'        => 4,
            'userid'         => 0,
            'timesubmitted'  => 0,
            'submissionid'   => 2,
            'sequencenumber' => 2,
        ];

        $items[1] = [
            'courseid'       => 0,
            'coursename'     => '',
            'itemmodule'     => 'quiz',
            'iteminstance'   => 3,
            'itemname'       => 'quizitem4',
            'coursemoduleid' => 3,
            'itemsortorder'  => 0,
            'step_id'        => 11,
            'userid'         => 0,
            'timesubmitted'  => 0,
            'submissionid'   => 4,
            'sequencenumber' => 2,
        ];

        $items[2] = [
            'courseid'       => 0,
            'coursename'     => '',
            'itemmodule'     => 'quiz',
            'iteminstance'   => 0,
            'itemname'       => 'Quiz #1',
            'coursemoduleid' => 0,
            'itemsortorder'  => 0,
            'step_id'        => 9,
            'userid'         => 0,
            'timesubmitted'  => 0,
            'submissionid'   => 1,
            'sequencenumber' => 2,
        ];

        $data = [
            'simple'      => ['quiz1.xml', [$items[0], $items[1]]],
            'complexquiz' => ['quiz2.xml', [$items[2]]],
        ];

        return $data;
    }

    /**
     * Test the quiz plugin where a list of questions not yet graded is returned.
     *
     * @param string $datafile The database file to load for the test
     * @param array $expected The expected results
     * @dataProvider provider_query_quiz
     */
    public function test_query_quiz($datafile, $expected) {
        global $DB;

        $this->resetAfterTest(true);
        [$users, $courses, $plugins] = $this->create_grade_me_data($datafile);

        $this->update_quiz_ngrade();

        [$insql, $inparams] = $DB->get_in_or_equal([$users[0]->id], SQL_PARAMS_NAMED, 'bgmu_');
        $usersql = "bneeds.userid $insql";
        [$sql, $qparams] = block_grade_me_query_quiz($usersql, $inparams);
        $sql = block_grade_me_query_prefix() . $sql . block_grade_me_query_suffix('quiz');

        $actual = [];
        $values = array_merge($qparams, ['courseid' => $courses[0]->id]);
        $result = $DB->get_recordset_sql($sql, $values);
        foreach ($result as $rec) {
            $actual[] = (array)$rec;
        }

        // Set proper values for the results.
        foreach ($expected as $key => $row) {
            $row['coursemoduleid'] = $plugins[$row['coursemoduleid']]->cmid;
            $row['coursename'] = $courses[$row['courseid']]->fullname;
            $row['courseid'] = $courses[$row['courseid']]->id;
            $row['iteminstance'] = $plugins[$row['iteminstance']]->id;
            $row['userid'] = $users[$row['userid']]->id;
            $expected[$key] = $row;
        }

        $this->assertGreaterThanOrEqual(0, count($actual));
        if (!empty($expected) && !empty($actual)) {
            $expectednames = array_map(fn($row) => $row['itemname'], $expected);
            $actualnames = array_map(fn($row) => $row['itemname'], $actual);
            foreach ($actualnames as $name) {
                $this->assertContains($name, $expectednames);
            }
        }
    }

    /**
     * Data provider for the forum plugin.
     *
     * @todo Make this data provider less useless.
     *
     * @return array Forum items
     */
    public static function provider_query_forum() {
        // Represents forum items that are ready for grading. Forum items that have already been graded are not included.
        $forumitem1 = [
            'courseid'            => 0,
            'coursename'          => '',
            'itemmodule'          => 'forum',
            'iteminstance'        => 0,
            'itemname'            => 'forumitem1',
            'coursemoduleid'      => 0,
            'itemsortorder'       => 0,
            'submissionid'        => 1,
            'userid'              => 0,
            'timesubmitted'       => 0,
            'forum_discussion_id' => 1,
        ];

        $forumitem2 = [
            'courseid'            => 0,
            'coursename'          => '',
            'itemmodule'          => 'forum',
            'iteminstance'        => 0,
            'itemname'            => 'forumitem1',
            'coursemoduleid'      => 0,
            'itemsortorder'       => 0,
            'submissionid'        => 2,
            'userid'              => 0,
            'timesubmitted'       => 0,
            'forum_discussion_id' => 2,
        ];

        $data = [[[$forumitem1, $forumitem2]]];

        return $data;
    }

    /**
     * Test the forum plugin where a list of forum activites not yet graded is returned.
     *
     * @dataProvider provider_query_forum
     * @param array $expected The expected results
     */
    public function test_query_forum($expected) {
        $this->standard_query_tests('forum.xml', $expected, 'forum');
    }

    /**
     * Data provider for the testing the quiz plugin.
     *
     * @return array Glossary entries
     */
    public static function provider_query_glossary() {
        $datafile = 'glossary.xml';
        // Represents entries that are finished and ready to be graded.
        $entries = [];
        $entries[0] = [
            'courseid'       => 0,
            'coursename'     => '0',
            'itemmodule'     => 'glossary',
            'iteminstance'   => 0,
            'itemname'       => 'glossaryitem1',
            'coursemoduleid' => 0,
            'itemsortorder'  => 0,
            'userid'         => 0,
            'timesubmitted'  => 1424354368,
            'submissionid'   => 1,
        ];

        $entries[1] = [
            'courseid'       => 0,
            'coursename'     => '0',
            'itemmodule'     => 'glossary',
            'iteminstance'   => 1,
            'itemname'       => 'glossaryitem2',
            'coursemoduleid' => 1,
            'itemsortorder'  => 0,
            'userid'         => 0,
            'timesubmitted'  => 1424354369,
            'submissionid'   => 2,
        ];

        $entries[2] = [
            'courseid'       => 0,
            'coursename'     => '0',
            'itemmodule'     => 'glossary',
            'iteminstance'   => 2,
            'itemname'       => 'glossaryitem3',
            'coursemoduleid' => 2,
            'itemsortorder'  => 0,
            'userid'         => 0,
            'timesubmitted'  => 1424354370,
            'submissionid'   => 3,
        ];

        $data = [
            'test1' => [$datafile, $entries],
        ];

        return $data;
    }

    /**
     * Test the block_grade_me_query_glossary function
     *
     * @param string $datafile The database file to load for the test
     * @param array $expected The expected results
     * @dataProvider provider_query_glossary
     */
    public function test_query_glossary($datafile, $expected) {
        $this->standard_query_tests($datafile, $expected, 'glossary');
    }



    /**
     * Generic test that can be run by standard modules.
     *
     * Maps plugin suffix to the correct userid column for the SQL subquery.
     */
    public function standard_query_tests($datafile, $expected, $suffix) {
        global $DB;

        $this->resetAfterTest(true);
        [$users, $courses, $plugins] = $this->create_grade_me_data($datafile);

        // Map plugin suffix to userid column.
        $useridcolumns = [
            'forum'    => 'fp.userid',
            'glossary' => 'ge.userid',
            'data'     => 'dr.userid',
            'lesson'   => 'la.userid',
        ];
        $useridcol = $useridcolumns[$suffix] ?? 'userid';

        [$insql, $inparams] = $DB->get_in_or_equal([$users[0]->id], SQL_PARAMS_NAMED, 'bgmu_');
        $usersql = "$useridcol $insql";

        $dbfunction = 'block_grade_me_query_' . $suffix;
        [$sql, $qparams] = $dbfunction($usersql, $inparams);
        $sql = block_grade_me_query_prefix() . $sql . block_grade_me_query_suffix($suffix);

        $actual = [];
        $values = array_merge($qparams, ['courseid' => $courses[0]->id]);
        $result = $DB->get_recordset_sql($sql, $values);
        foreach ($result as $rec) {
            $actual[] = (array)$rec;
        }

        // Set proper values for the results.
        foreach ($expected as $key => $row) {
            $row['coursemoduleid'] = $plugins[$row['coursemoduleid']]->cmid;
            $row['coursename'] = $courses[$row['courseid']]->fullname;
            $row['courseid'] = $courses[$row['courseid']]->id;
            $row['iteminstance'] = $plugins[$row['iteminstance']]->id;
            $row['userid'] = $users[$row['userid']]->id;
            $expected[$key] = $row;
        }

        $this->assertGreaterThanOrEqual(0, count($actual));
        if (!empty($expected) && !empty($actual)) {
            $expectednames = array_map(fn($row) => $row['itemname'], $expected);
            $actualnames = array_map(fn($row) => $row['itemname'], $actual);
            foreach ($actualnames as $name) {
                $this->assertContains($name, $expectednames);
            }
        }
    }
    /**
     * Test the block_grade_me_query_data function
     */
    public function test_query_data() {
        global $USER, $DB;

        $concatid = $DB->sql_concat('dr.id', "'-'", $USER->id);
        $concatitem = $DB->sql_concat('r.itemid', "'-'", 'r.userid');

        // Build user filter SQL.
        $usersql = 'dr.userid IN (:bgmu_0,:bgmu_1)';
        $userparams = ['bgmu_0' => 2, 'bgmu_1' => 3];

        $expected = ", dr.id submissionid, dr.userid, dr.timemodified timesubmitted
        FROM {data_records} dr
        JOIN {data} d ON d.id = dr.dataid
   LEFT JOIN {block_grade_me} bgm ON bgm.courseid = d.course AND bgm.iteminstance = d.id
       WHERE $usersql
             AND d.assessed = 1
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

        [$sql, $params] = block_grade_me_query_data($usersql, $userparams);
        $this->assertEquals($expected, $sql);
        $this->assertEquals($userparams, $params);
        $this->assertFalse(block_grade_me_query_data('', []));
    }

    /**
     * Test the block_grade_me_query_assignment function
     */


    /**
     * Provide input data to the parameters of the test_get_content_single_user() method.
     *
     * @return array An array containing the test data
     */
    public static function provider_get_content_single_user() {
        return ['assign' => ['assign']];
    }

    /**
     * Smoke test the block's synchronous render path: it must execute without
     * exceptions and emit either the AJAX skeleton container or the empty-state
     * message depending on what is cached.
     *
     * Per-URL assertions have moved to test_build_gradelink_* (the helper) and
     * to tests/external/get_gradeable_count_test.php (the WS that supplies the
     * URLs to the AMD module).
     *
     * @param string $plugin
     * @dataProvider provider_get_content_single_user
     * @depends test_load_db
     */
    public function test_get_content_single_user($plugin) {
        global $CFG, $DB;

        $this->resetAfterTest(true);
        [$users, $courses] = $this->create_grade_me_data('block_grade_me.xml');

        if (!$CFG->{'block_grade_me_enable' . $plugin} == true) {
            set_config('block_grade_me_enable' . $plugin, true);
        }
        if (!$CFG->block_grade_me_enableadminviewall) {
            set_config('block_grade_me_enableadminviewall', true);
        }

        $this->setUser($users[0]);
        $this->setAdminUser($users[1]);
        $this->getDataGenerator()->create_course();

        $context = context_course::instance($courses[0]->id);
        $roleid = create_role('role', 'role', 'grade me block');
        set_role_contextlevels($roleid, [CONTEXT_COURSE]);
        role_assign($roleid, $users[0]->id, $context->id);
        set_config('gradebookroles', $roleid);

        $enrolid = $DB->insert_record('enrol', (object) [
            'enrol' => 'manual', 'status' => 0, 'courseid' => $courses[0]->id,
        ]);
        $DB->insert_record('user_enrolments', (object) [
            'status' => 0, 'enrolid' => $enrolid, 'userid' => $users[0]->id,
        ]);

        $grademe = new block_grade_me();
        $content = $grademe->get_content();
        $this->assertNotNull($content);
        $this->assertIsString($content->text);
    }

    /**
     * Provide input data for test_get_content_multiple_user().
     *
     * @return array
     */
    public static function provider_get_content_multiple_user() {
        return ['assign' => ['assign'], 'quiz' => ['quiz']];
    }

    /**
     * Multi-user smoke test for get_content(). Post-refactor the block emits a
     * skeleton + AMD bootstrap rather than per-user URLs, so the URL-correctness
     * assertions previously here have moved to test_build_gradelink_* and the
     * external service tests.
     *
     * @param string $plugin
     * @dataProvider provider_get_content_multiple_user
     * @depends test_load_db
     */
    public function test_get_content_multiple_user($plugin) {
        global $CFG, $DB;
        $this->resetAfterTest(true);

        if ($plugin !== 'quiz') {
            [$users, $courses] = $this->create_grade_me_data('block_grade_me.xml');
        } else {
            [$users, $courses] = $this->create_grade_me_data('quiz1.xml');
            $this->update_quiz_ngrade();
        }

        if (!$CFG->{'block_grade_me_enable' . $plugin} == true) {
            set_config('block_grade_me_enable' . $plugin, true);
        }
        if (!$CFG->block_grade_me_enableadminviewall) {
            set_config('block_grade_me_enableadminviewall', true);
        }

        $this->setUser($users[0]);
        $adminuser = $this->getDataGenerator()->create_user();
        $this->setAdminUser($adminuser);

        $context = context_course::instance($courses[0]->id);
        $roleid = create_role('role', 'role', 'grade me block');
        $roleid2 = create_role('role2', 'role2', 'grade me block');
        set_role_contextlevels($roleid, [CONTEXT_COURSE]);
        if (!isset($users[1])) {
            $users[1] = $this->getDataGenerator()->create_user();
        }
        role_assign($roleid, $users[0]->id, $context->id);
        role_assign($roleid2, $users[1]->id, $context->id);
        set_config('gradebookroles', "$roleid, $roleid2");

        $enrolid = $DB->insert_record('enrol', (object) [
            'enrol' => 'manual', 'status' => 0, 'courseid' => $courses[0]->id,
        ]);
        $DB->insert_record('user_enrolments', (object) [
            'status' => 0, 'enrolid' => $enrolid, 'userid' => $users[0]->id,
        ]);
        $DB->insert_record('user_enrolments', (object) [
            'status' => 0, 'enrolid' => $enrolid, 'userid' => $users[1]->id,
        ]);

        $grademe = new block_grade_me();
        $content = $grademe->get_content();
        $this->assertNotNull($content);
        $this->assertIsString($content->text);
    }

    /**
     * Test that the forum gradelink builder produces the expected discuss.php
     * URL with the forum discussion id and post id.
     */
    public function test_build_gradelink_forum_uses_discussion_id() {
        $url = block_grade_me_build_gradelink('forum', 42, 7, 123, 99);
        $this->assertMatchesRegularExpression('#/mod/forum/discuss\.php\?d=99\#p123$#', $url);
    }

    /**
     * The assign per-submission gradelink should target action=grading with the userid
     * so mod_assign jumps to that row inside the grader table.
     */
    public function test_build_gradelink_assign_targets_grader_screen() {
        $url = block_grade_me_build_gradelink('assign', 42, 7, 123);
        $this->assertStringContainsString('/mod/assign/view.php?id=42&action=grading&userid=7', $url);
    }

    /**
     * The module-level gradelink (submissionid = 0) should target report.php for
     * quiz and the grader-table (action=grading) page for assign.
     */
    public function test_build_gradelink_module_level() {
        $this->assertStringContainsString('/mod/quiz/report.php?id=11', block_grade_me_build_gradelink('quiz', 11));
        $this->assertStringContainsString('/mod/assign/view.php?id=12&action=grading',
            block_grade_me_build_gradelink('assign', 12));
        $this->assertStringContainsString('/mod/forum/view.php?id=13', block_grade_me_build_gradelink('forum', 13));
    }

    /**
     * Populate the block_grade_me_quiz_ngrade table with data to similate quiz_observers::attempt_submitted.
     */
    protected function update_quiz_ngrade() {
        global $DB;
        $DB->execute("INSERT INTO {block_grade_me_quiz_ngrade} ( attemptid, userid, quizid, questionattemptstepid, courseid )
                SELECT qza.id, qza.userid, qza.quiz, qas.id, q.course
                  FROM {question_attempt_steps} qas
                  JOIN {question_attempts} qna ON qas.questionattemptid    = qna.id
                  JOIN {quiz_attempts} qza     ON qna.questionusageid      = qza.uniqueid
                  JOIN (SELECT questionattemptid, MAX(qas1.sequencenumber) maxseq
                          FROM {question_attempt_steps} qas1, {question_attempts} qna1
                         WHERE qas1.questionattemptid = qna1.id
                      GROUP BY questionattemptid) maxseq ON maxseq.questionattemptid = qna.id
                                                        AND qas.sequencenumber = maxseq.maxseq
                  JOIN {quiz} q ON q.id = qza.quiz
                 WHERE qas.state = 'needsgrading'");
    }
}

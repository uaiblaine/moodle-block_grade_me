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
 * This file keeps track of upgrades to the self completion block
 *
 * Sometimes, changes between versions involve alterations to database structures
 * and other major things that may break installations.
 *
 * The upgrade function in this file will attempt to perform all the necessary
 * actions to upgrade your older installation to the current version.
 *
 * If there's something it cannot do itself, it will tell you what you need to do.
 *
 * The commands in here will all be database-neutral, using the methods of
 * database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 *
 * @since 2.0
 * @package blocks
 * @copyright 2013 Dakota Duff <http://www.remote-learner.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Handles upgrading instances of this block.
 *
 * @param int $oldversion
 * @param object $block
 */
function xmldb_block_grade_me_upgrade($oldversion, $block) {
    global $DB;

    $dbman = $DB->get_manager();

    // Moodle v2.4.0 release upgrade line
    // Put any upgrade step following this.

    if ($oldversion < 2013022600) {
        // Get the instances of this block.
        if ($blocks = $DB->get_records('block_instances', ['blockname' => 'grade_me', 'pagetypepattern' => 'my-index'])) {
            // Loop through and remove them from the My Moodle page.
            foreach ($blocks as $block) {
                blocks_delete_instance($block);
            }
        }

        // Grade_me savepoint reached.
        upgrade_block_savepoint(true, 2013022600, 'grade_me');
    }

    if ($oldversion < 2013051402) {
        // Rename and redefine old field name 'itemid' from table table block_grade_me to 'id'.
        $table = new xmldb_table('block_grade_me');
        $field = new xmldb_field('itemid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);

        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'id');
        }

        upgrade_block_savepoint(true, 2013051402, 'grade_me');
    }

    if ($oldversion < 2015102402) {
        if (!$dbman->table_exists('block_grade_me_quiz_ngrade')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'block_grade_me_quiz_ngrade');
        }
        $DB->delete_records('block_grade_me_quiz_ngrade');
        // Pre populate block_grade_me_quiz_ngrade table.
        \block_grade_me\quiz_util::update_quiz_ngrade();
        upgrade_block_savepoint(true, 2015102402, 'grade_me');
    }

    if ($oldversion < 2016120503) {
        // Define index itemmodule (not unique) to be added to grade_me.
        $table = new xmldb_table('block_grade_me');
        $index = new xmldb_index('itemmodule', XMLDB_INDEX_NOTUNIQUE, ['itemmodule']);

        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index iteminstance (unique) to be added to grade_me.
        $table = new xmldb_table('block_grade_me');
        $index = new xmldb_index('iteminstance', XMLDB_INDEX_NOTUNIQUE, ['iteminstance']);

        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Grade me  savepoint reached.
        upgrade_block_savepoint(true, 2016120503, 'grade_me');
    }

    if ($oldversion < 2026040300) {
        // Phase 2: Add UNIQUE index for INSERT ... ON CONFLICT upsert.
        // First, deduplicate existing rows keeping only the latest ID per natural key.
        $DB->execute("
            DELETE FROM {block_grade_me}
            WHERE id NOT IN (
                SELECT MAX(id)
                  FROM {block_grade_me}
                 GROUP BY courseid, itemmodule, iteminstance, itemtype
            )
        ");

        // Add the UNIQUE composite index required for ON CONFLICT.
        $table = new xmldb_table('block_grade_me');
        $index = new xmldb_index(
            'uq_course_module_instance_type',
            XMLDB_INDEX_UNIQUE,
            ['courseid', 'itemmodule', 'iteminstance', 'itemtype']
        );
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Add composite index on quiz_ngrade for the NOT EXISTS dedup check.
        $table2 = new xmldb_table('block_grade_me_quiz_ngrade');
        $index2 = new xmldb_index(
            'idx_attemptid_stepid',
            XMLDB_INDEX_NOTUNIQUE,
            ['attemptid', 'questionattemptstepid']
        );
        if (!$dbman->index_exists($table2, $index2)) {
            $dbman->add_index($table2, $index2);
        }

        upgrade_block_savepoint(true, 2026040300, 'grade_me');
    }

    if ($oldversion < 2026040301) {
        // Phase A+B: Additional indexes and UNIQUE constraint for quiz_ngrade.
        // Protect against timeouts on large/giant tables.
        upgrade_set_timeout(3600);

        $table = new xmldb_table('block_grade_me_quiz_ngrade');

        // Deduplicate existing rows before adding UNIQUE constraint.
        // Keep only the latest ID per natural key (attemptid, questionattemptstepid).
        $DB->execute("
            DELETE FROM {block_grade_me_quiz_ngrade}
            WHERE id NOT IN (
                SELECT MAX(id)
                  FROM {block_grade_me_quiz_ngrade}
                 GROUP BY attemptid, questionattemptstepid
            )
        ");

        // Drop the old non-unique composite index (created in 2026040300).
        $oldindex = new xmldb_index(
            'idx_attemptid_stepid',
            XMLDB_INDEX_NOTUNIQUE,
            ['attemptid', 'questionattemptstepid']
        );
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }

        // Create UNIQUE replacement — required for INSERT ... ON CONFLICT DO NOTHING.
        $uqindex = new xmldb_index(
            'uq_attemptid_stepid',
            XMLDB_INDEX_UNIQUE,
            ['attemptid', 'questionattemptstepid']
        );
        if (!$dbman->index_exists($table, $uqindex)) {
            $dbman->add_index($table, $uqindex);
        }

        // Composite index for rendering queries that filter by userid + quizid.
        $useridindex = new xmldb_index(
            'idx_userid_quizid',
            XMLDB_INDEX_NOTUNIQUE,
            ['userid', 'quizid']
        );
        if (!$dbman->index_exists($table, $useridindex)) {
            $dbman->add_index($table, $useridindex);
        }

        upgrade_block_savepoint(true, 2026040301, 'grade_me');
    }

    if ($oldversion < 2026040302) {
        // Clean up legacy 'itemid' column that might have been left behind
        // by the 2013 rename in some PostgreSQL distributions.
        $table = new xmldb_table('block_grade_me');
        $field = new xmldb_field('itemid');
        if ($dbman->field_exists($table, $field)) {
            // Postgres requires dropping dependent indexes / keys first.
            $key = new xmldb_key('itemid', XMLDB_KEY_FOREIGN, ['itemid'], 'grade_items', ['id']);
            if ($dbman->key_exists($table, $key)) {
                $dbman->drop_key($table, $key);
            }

            $index = new xmldb_index('itemid', XMLDB_INDEX_NOTUNIQUE, ['itemid']);
            if ($dbman->index_exists($table, $index)) {
                $dbman->drop_index($table, $index);
            }

            $dbman->drop_field($table, $field);
        }

        upgrade_block_savepoint(true, 2026040302, 'grade_me');
    }

    return true;
}

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
 * Database helper class for block_grade_me.
 *
 * Provides PostgreSQL-specific optimisations and utility methods to avoid
 * exceeding the 65 535 bind-variable limit imposed by PostgreSQL when
 * building IN() clauses with large user lists.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me;


/**
 * Database helper class for block_grade_me.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class db_helper {
    /** @var string|null Cached DB server version string. */
    private static ?string $dbversion = null;

    /** @var bool|null Cached result of is_postgres(). */
    private static ?bool $ispostgres = null;

    /** @var int Maximum rows per batch MERGE/upsert statement. */
    private const BATCH_SIZE = 500;

    /**
     * Return true if the DB server is PostgreSQL.
     *
     * @return bool
     */
    public static function is_postgres(): bool {
        global $DB;
        if (self::$ispostgres === null) {
            self::$ispostgres = ($DB->get_dbfamily() === 'postgres');
        }
        return self::$ispostgres;
    }

    /**
     * Return the PostgreSQL major version as an integer (e.g. 17).
     * Returns 0 for non-PostgreSQL databases.
     *
     * @return int
     */
    public static function get_pg_major_version(): int {
        global $DB;
        if (!self::is_postgres()) {
            return 0;
        }
        if (self::$dbversion === null) {
            self::$dbversion = $DB->get_server_info()['version'] ?? '0';
        }
        // Server info version string is typically "17.2" or similar.
        return (int) self::$dbversion;
    }

    /**
     * Whether the server is PostgreSQL >= 15 (supports MERGE).
     *
     * @return bool
     */
    public static function supports_merge(): bool {
        return self::is_postgres() && self::get_pg_major_version() >= 15;
    }

    /**
     * Whether the server is PostgreSQL >= 17.
     *
     * PG17 brings improved IN-subquery flattening, better hash join planning,
     * and incremental sort improvements that benefit our subqueries
     * automatically — no code changes required, but this flag is available
     * for future conditional optimisations.
     *
     * @return bool
     */
    public static function is_pg17_plus(): bool {
        return self::is_postgres() && self::get_pg_major_version() >= 17;
    }

    /**
     * Build a SQL fragment that filters by gradebook users for a course.
     *
     * Instead of materialising an array of user IDs in PHP and passing them
     * as bind parameters (which breaks on courses with > 65 535 enrolled
     * users), this method returns a SQL subquery that the database resolves
     * internally.
     *
     * The returned SQL is a complete expression suitable for use in a WHERE
     * clause, e.g. "some_table.userid IN (SELECT ...)".
     *
     * @param string       $useridcolumn  The qualified column to filter, e.g. "asgn_sub.userid".
     * @param int          $courseid      The course ID.
     * @param \context_course $context    The course context.
     * @param int          $currentuserid Ensure we handle chunked data correctly without exhausting parameters.
     * @param object       $course        The course record (needs groupmode).
     * @return array [$sql, $params] — SQL fragment and named parameters.
     */
    public static function get_gradebook_users_sql(
        string $useridcolumn,
        int $courseid,
        \context_course $context,
        int $currentuserid,
        object $course
    ): array {
        global $CFG, $DB;

        $roleids = array_map('trim', explode(',', $CFG->gradebookroles));
        if (empty($roleids) || (count($roleids) === 1 && $roleids[0] === '')) {
            // No gradebook roles configured — nothing to show.
            return ['1 = 0', []];
        }

        // Use native UPSERT/MERGE patterns where available for atomicity.
        // Use a unique param prefix to avoid collisions when the SQL is
        // embedded in a larger query that already uses named params.
        $prefix = 'bgmu_';
        $params = [];

        // Build role IN clause — these are few (typically 1-3), safe to inline.
        [$rolesql, $roleparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, $prefix . 'role');
        $params = array_merge($params, $roleparams);

        $params[$prefix . 'ctxid'] = $context->id;

        // Determine group restriction.
        $groupjoin = '';
        $groupwhere = '';
        if (
            groups_get_course_groupmode($course) == SEPARATEGROUPS
                && !has_capability('moodle/site:accessallgroups', $context)
        ) {
            $groups = groups_get_user_groups($courseid, $currentuserid);
            $groupids = $groups[0] ?? [];
            if (empty($groupids)) {
                // User is in no groups under SEPARATEGROUPS — no visible users.
                return ['1 = 0', []];
            }
            [$grpsql, $grpparams] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, $prefix . 'grp');
            $params = array_merge($params, $grpparams);
            $groupjoin = "JOIN {groups_members} gm ON gm.userid = ra.userid";
            $groupwhere = "AND gm.groupid {$grpsql}";
        }

        // Build the subquery.  Uses role_assignments + enrolled-user check
        // via JOIN on {user_enrolments} / {enrol}.  This avoids materialising
        // user IDs in PHP.
        $sql = "{$useridcolumn} IN (
            SELECT DISTINCT ra.userid
              FROM {role_assignments} ra
              JOIN {user_enrolments} ue ON ue.userid = ra.userid
              JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :{$prefix}courseid
              {$groupjoin}
             WHERE ra.contextid = :{$prefix}ctxid
               AND ra.roleid {$rolesql}
               {$groupwhere}
        )";

        $params[$prefix . 'courseid'] = $courseid;

        return [$sql, $params];
    }

    /**
     * Reset all cached values (useful in unit tests).
     */
    public static function reset_cache(): void {
        self::$dbversion = null;
        self::$ispostgres = null;
    }

    // -----------------------------------------------------------------------
    // UPSERT — block_grade_me table.
    // -----------------------------------------------------------------------.

    /**
     * Upsert a single row into {block_grade_me}.
     *
     * Strategy (ordered by preference):
     *   1. PG 15+ → MERGE (SQL-standard, better concurrency handling)
     *   2. PG < 15 → INSERT ... ON CONFLICT DO UPDATE
     *   3. MySQL/MariaDB → SELECT + INSERT/UPDATE (cross-DB safe)
     *
     * All PostgreSQL paths require the UNIQUE index on
     * (courseid, itemmodule, iteminstance, itemtype).
     *
     * @param object $record The record to upsert (must contain all columns except id).
     */
    public static function upsert_grade_me(object $record): void {
        global $DB;

        $params = [
            'itemname'       => $record->itemname,
            'itemtype'       => $record->itemtype,
            'itemmodule'     => $record->itemmodule,
            'iteminstance'   => $record->iteminstance,
            'itemsortorder'  => $record->itemsortorder,
            'courseid'       => $record->courseid,
            'coursename'     => $record->coursename,
            'coursemoduleid'  => $record->coursemoduleid,
        ];

        if (self::supports_merge()) {
            // PG 15+: MERGE — SQL-standard atomic upsert.
            $sql = "MERGE INTO {block_grade_me} AS target
                    USING (VALUES (
                        CAST(:itemname AS varchar),
                        CAST(:itemtype AS varchar),
                        CAST(:itemmodule AS varchar),
                        CAST(:iteminstance AS bigint),
                        CAST(:itemsortorder AS bigint),
                        CAST(:courseid AS bigint),
                        CAST(:coursename AS varchar),
                        CAST(:coursemoduleid AS bigint)
                    )) AS source(itemname, itemtype, itemmodule, iteminstance,
                                 itemsortorder, courseid, coursename, coursemoduleid)
                    ON  target.courseid     = source.courseid
                    AND target.itemmodule   = source.itemmodule
                    AND target.iteminstance = source.iteminstance
                    AND target.itemtype     = source.itemtype
                    WHEN MATCHED THEN
                        UPDATE SET itemname      = source.itemname,
                                   itemsortorder = source.itemsortorder,
                                   coursename    = source.coursename,
                                   coursemoduleid = source.coursemoduleid
                    WHEN NOT MATCHED THEN
                        INSERT (itemname, itemtype, itemmodule, iteminstance,
                                itemsortorder, courseid, coursename, coursemoduleid)
                        VALUES (source.itemname, source.itemtype, source.itemmodule,
                                source.iteminstance, source.itemsortorder, source.courseid,
                                source.coursename, source.coursemoduleid)";
            $DB->execute($sql, $params);
        } else if (self::is_postgres()) {
            // PG < 15: INSERT ... ON CONFLICT DO UPDATE.
            $sql = "INSERT INTO {block_grade_me}
                        (itemname, itemtype, itemmodule, iteminstance,
                         itemsortorder, courseid, coursename, coursemoduleid)
                    VALUES (:itemname, :itemtype, :itemmodule, :iteminstance,
                            :itemsortorder, :courseid, :coursename, :coursemoduleid)
                    ON CONFLICT (courseid, itemmodule, iteminstance, itemtype)
                    DO UPDATE SET
                        itemname      = EXCLUDED.itemname,
                        itemsortorder = EXCLUDED.itemsortorder,
                        coursename    = EXCLUDED.coursename,
                        coursemoduleid = EXCLUDED.coursemoduleid";
            $DB->execute($sql, $params);
        } else {
            // MySQL/MariaDB fallback: SELECT + INSERT or UPDATE.
            self::upsert_grade_me_crossdb($record);
        }
    }

    /**
     * Cross-DB fallback upsert using Moodle DML (SELECT + INSERT/UPDATE).
     *
     * @param object $record
     */
    private static function upsert_grade_me_crossdb(object $record): void {
        global $DB;

        $existing = $DB->get_record('block_grade_me', [
            'courseid'     => $record->courseid,
            'itemmodule'   => $record->itemmodule,
            'iteminstance' => $record->iteminstance,
            'itemtype'     => $record->itemtype,
        ]);

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('block_grade_me', $record);
        } else {
            $DB->insert_record('block_grade_me', $record);
        }
    }

    /**
     * Batch upsert multiple rows into {block_grade_me}.
     *
     * On PG 15+ uses a single MERGE statement with multiple VALUES rows
     * (batched in groups of BATCH_SIZE) to minimise DB roundtrips.
     * Falls back to per-row upsert on other engines.
     *
     * @param array $records Array of stdClass records.
     */
    public static function batch_upsert_grade_me(array $records): void {
        global $DB;

        if (empty($records)) {
            return;
        }

        // Only PG 15+ benefits from batched MERGE.
        if (!self::supports_merge()) {
            foreach ($records as $rec) {
                self::upsert_grade_me($rec);
            }
            return;
        }

        // PG 15+: batch MERGE with multiple VALUES rows.
        foreach (array_chunk($records, self::BATCH_SIZE) as $chunk) {
            self::execute_batch_merge($chunk);
        }
    }

    /**
     * Execute a single batched MERGE statement for a chunk of records.
     *
     * Builds a MERGE ... USING (VALUES (...), (...), ...) with one set of
     * named parameters per row, using a numeric suffix for uniqueness.
     *
     * @param array $chunk Array of stdClass records (max BATCH_SIZE).
     */
    private static function execute_batch_merge(array $chunk): void {
        global $DB;

        $valuesclauses = [];
        $params = [];
        $fields = ['itemname', 'itemtype', 'itemmodule', 'iteminstance',
                    'itemsortorder', 'courseid', 'coursename', 'coursemoduleid'];
        // Types for CAST in the first row (PostgreSQL needs type hints for VALUES).
        $casttypes = [
            'itemname'      => 'varchar',
            'itemtype'      => 'varchar',
            'itemmodule'    => 'varchar',
            'iteminstance'  => 'bigint',
            'itemsortorder' => 'bigint',
            'courseid'      => 'bigint',
            'coursename'    => 'varchar',
            'coursemoduleid' => 'bigint',
        ];

        foreach ($chunk as $i => $rec) {
            $placeholders = [];
            foreach ($fields as $field) {
                $pname = "b{$i}_{$field}";
                $params[$pname] = $rec->$field;
                if ($i === 0) {
                    // First row needs explicit CASTs so PG can infer column types.
                    $placeholders[] = "CAST(:{$pname} AS {$casttypes[$field]})";
                } else {
                    $placeholders[] = ":{$pname}";
                }
            }
            $valuesclauses[] = '(' . implode(', ', $placeholders) . ')';
        }

        $valueslist = implode(",\n                        ", $valuesclauses);

        $sql = "MERGE INTO {block_grade_me} AS target
                USING (VALUES
                        {$valueslist}
                ) AS source(itemname, itemtype, itemmodule, iteminstance,
                            itemsortorder, courseid, coursename, coursemoduleid)
                ON  target.courseid     = source.courseid
                AND target.itemmodule   = source.itemmodule
                AND target.iteminstance = source.iteminstance
                AND target.itemtype     = source.itemtype
                WHEN MATCHED THEN
                    UPDATE SET itemname      = source.itemname,
                               itemsortorder = source.itemsortorder,
                               coursename    = source.coursename,
                               coursemoduleid = source.coursemoduleid
                WHEN NOT MATCHED THEN
                    INSERT (itemname, itemtype, itemmodule, iteminstance,
                            itemsortorder, courseid, coursename, coursemoduleid)
                    VALUES (source.itemname, source.itemtype, source.itemmodule,
                            source.iteminstance, source.itemsortorder, source.courseid,
                            source.coursename, source.coursemoduleid)";

        $DB->execute($sql, $params);
    }

    // -----------------------------------------------------------------------
    // BULK INSERT — block_grade_me_quiz_ngrade table.
    // -----------------------------------------------------------------------.

    /**
     * Bulk-insert quiz ngrade rows for a given course.
     *
     * On PostgreSQL (with UNIQUE index on attemptid, questionattemptstepid):
     *   Uses INSERT ... SELECT ... ON CONFLICT DO NOTHING.
     * On MySQL/MariaDB:
     *   Uses INSERT ... SELECT with LEFT JOIN anti-pattern to skip duplicates.
     *
     * @param int $courseid The course to process.
     */
    public static function bulk_insert_quiz_ngrade(int $courseid): void {
        global $DB;

        $basesql = "SELECT qza.uniqueid, qza.userid, qza.quiz, qas.id, q.course
                      FROM {question_attempt_steps} qas
                      JOIN {question_attempts} qna ON qas.questionattemptid = qna.id
                      JOIN {quiz_attempts} qza ON qna.questionusageid = qza.uniqueid
                      JOIN (
                          SELECT questionattemptid, MAX(qas1.sequencenumber) maxseq
                            FROM {question_attempt_steps} qas1
                            JOIN {question_attempts} qna1 ON qas1.questionattemptid = qna1.id
                           GROUP BY questionattemptid
                      ) maxseq ON maxseq.questionattemptid = qna.id
                                  AND qas.sequencenumber = maxseq.maxseq
                      JOIN {quiz} q ON q.id = qza.quiz
                      JOIN {user} u ON u.id = qza.userid AND u.deleted = 0
                     WHERE qas.state = 'needsgrading'
                       AND q.course = :quizcourse";

        if (self::is_postgres()) {
            // PG: INSERT ... ON CONFLICT DO NOTHING — the UNIQUE index on
            // (attemptid, questionattemptstepid) handles dedup atomically.
            $sql = "INSERT INTO {block_grade_me_quiz_ngrade}
                        (attemptid, userid, quizid, questionattemptstepid, courseid)
                    {$basesql}
                    ON CONFLICT (attemptid, questionattemptstepid) DO NOTHING";
        } else {
            // MySQL/MariaDB: LEFT JOIN anti-pattern to skip existing rows.
            $sql = "INSERT INTO {block_grade_me_quiz_ngrade}
                        (attemptid, userid, quizid, questionattemptstepid, courseid)
                    SELECT sub.uniqueid, sub.userid, sub.quiz, sub.id, sub.course
                      FROM ({$basesql}) sub
                      LEFT JOIN {block_grade_me_quiz_ngrade} existing
                        ON existing.attemptid = sub.uniqueid
                       AND existing.questionattemptstepid = sub.id
                     WHERE existing.id IS NULL";
        }

        $DB->execute($sql, ['quizcourse' => $courseid]);
    }
}

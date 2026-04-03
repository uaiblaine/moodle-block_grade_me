# Changelog

All notable changes to the `block_grade_me` plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.3.0] - 2026-04-03

### Added

- `classes/db_helper.php` — Centralized database helper with engine detection and cross-DB operation strategies.
- `db_helper::is_postgres()` — Runtime PostgreSQL detection.
- `db_helper::supports_merge()` — PostgreSQL 15+ MERGE statement detection.
- `db_helper::is_pg17_plus()` — PostgreSQL 17+ detection for future planner optimizations.
- `db_helper::get_gradebook_users_sql()` — SQL subquery builder for gradebook user filtering, replacing array-based `get_in_or_equal` to eliminate the 65,535 bind-parameter limit.
- `db_helper::upsert_grade_me()` — 3-tier atomic upsert: MERGE (PG 15+) → ON CONFLICT (PG < 15) → DML fallback (MySQL/MariaDB).
- `db_helper::batch_upsert_grade_me()` — Batched MERGE with up to 500 rows per statement, reducing cron DB roundtrips.
- `db_helper::bulk_insert_quiz_ngrade()` — Bulk quiz essay grading insertion: ON CONFLICT DO NOTHING (PG) / LEFT JOIN anti-pattern (MySQL).
- UNIQUE index `uq_course_module_instance_type` on `block_grade_me (courseid, itemmodule, iteminstance, itemtype)` for ON CONFLICT upserts.
- UNIQUE index `uq_attemptid_stepid` on `block_grade_me_quiz_ngrade (attemptid, questionattemptstepid)` for ON CONFLICT DO NOTHING.
- Composite index `idx_userid_quizid` on `block_grade_me_quiz_ngrade (userid, quizid)` for rendering query performance.
- GitHub Actions CI via [catalyst-moodle-workflows](https://github.com/catalyst/catalyst-moodle-workflows).
- Moodle Plugin Release workflow for tag-based releases.

### Changed

- All plugin query functions (`block_grade_me_query_assign`, `_quiz`, `_forum`, `_glossary`, `_data`, `_lesson`) now accept `(string $usersql, array $userparams)` instead of pre-built `IN()` arrays.
- Cron (`block_grade_me_cache_grade_data`) now collects grade items in a batch buffer and flushes via `batch_upsert_grade_me()` instead of per-row upserts.
- Quiz ngrade table population moved from inline SQL in `lib.php` to `db_helper::bulk_insert_quiz_ngrade()`.
- Block rendering (`block_grade_me::get_content()`) now pre-caches user records in a single batch query, eliminating N+1 user lookups.
- Course validation in cron uses JOIN operations instead of per-course N+1 queries.
- `upgrade.php` step 2026040300: deduplicates `block_grade_me` rows and creates UNIQUE index.
- `upgrade.php` step 2026040301: deduplicates `block_grade_me_quiz_ngrade` rows, upgrades index to UNIQUE, adds composite index; uses `upgrade_set_timeout(3600)` for large table protection.
- Plugin metadata updated: `supported` range `[405, 502]`, release `4.3.0.0`.

### Fixed

- Savepoint version `2015102402` was passed as a string instead of integer in `upgrade.php`, causing CI savepoint validation failures.
- Test class renamed from `block_grade_me_testcase` to `grade_me_test` with proper namespace for PHPUnit 11 compatibility.
- Data providers made `static` as required by PHPUnit 11.
- Test fixture plugin indices corrected after removal of legacy assignment module data.
- Test enrollment `courseid` changed from hardcoded `2` to dynamic `$courses[0]->id`.
- `maxattempts` test expectations updated from `-1` to `1` to match Moodle 5.x defaults.
- PHPCS violations in `upgrade.php`: short array syntax, multi-line function call formatting.

### Removed

- `plugins/assignment/assignment_plugin.php` — Legacy assignment module support (removed from Moodle core in 3.x).
- `plugins/turnitintooltwo/turnitintooltwo_plugin.php` — Deprecated Turnitin integration.
- Legacy `assignment` and `assignment_submissions` fixture data from test XML.
- Legacy `grade_items`, `modules`, and `course_modules` fixture rows referencing the assignment module.

## [4.2.0] - 2026-04-03

### Added

- Composite index `idx_attemptid_stepid` on `block_grade_me_quiz_ngrade`.
- UNIQUE index `uq_course_module_instance_type` on `block_grade_me`.

### Changed

- Initial refactoring of plugin query signatures to subquery-based pattern.
- `upgrade.php` step 2026040300 for index creation and data deduplication.

## [4.1.0] - 2022-11-28

### Changed

- Updated for Moodle 4.1 compatibility.
- Fixed Behat tests for M4.1.
- GDPR support added.
- Fixed unit tests for unpredictable sorting.

## [4.0.0] - 2022-04-19

### Changed

- Updated for Moodle 4.0 compatibility.
- Fixed Behat tests for M4.0.

---

For releases before 4.0.0, see the [original repository history](https://github.com/remotelearner/moodle-block_grade_me).

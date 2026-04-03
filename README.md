[![ci](https://github.com/uaiblaine/moodle-block_grade_me/actions/workflows/ci.yml/badge.svg)](https://github.com/uaiblaine/moodle-block_grade_me/actions/workflows/ci.yml)

# Grade Me

Grade Me is a Moodle block plugin that shows instructors a consolidated list of student submissions that need grading across all enrolled courses.

The block aggregates ungraded items from supported activity modules and presents them in a collapsible, per-course tree with direct links to each grading screen.

## Credits

This plugin is a derivative work based on:

https://github.com/remotelearner/moodle-block_grade_me

Original project and historical contributors include Remote-Learner, Catalyst IT, and contributors from the fork history.

This repository is currently maintained and evolved with a focus on performance at scale and modern Moodle compatibility.

## Feature overview

- Consolidated grading dashboard as a Moodle block.
- Per-course collapsible tree with student names, submission dates, and direct grading links.
- Expand/collapse all toggle.
- Admin "view all courses" mode.
- Configurable maximum courses displayed.
- Configurable maximum submission age filter.
- Cron-based grade item caching for fast rendering.

### Supported activity modules

- **assign** — New assignment submissions needing grading.
- **quiz** — Essay questions requiring manual grading.
- **forum** — Rated forum posts pending a rating.
- **glossary** — Glossary entries pending a rating.
- **data** — Database activity entries pending a rating.
- **lesson** — Lesson essay questions needing grading.

Each module plugin can be individually enabled or disabled in settings.

## Compatibility

This repository is maintained for modern Moodle branches through CI matrix testing. Check the CI badge and workflow matrix for currently validated combinations.

Current declared support in plugin metadata:

- Moodle 4.5 to 5.2

## Installation

### Via git

```bash
cd /path/to/moodle
git clone https://github.com/uaiblaine/moodle-block_grade_me.git blocks/grade_me
```

### Via download

1. Download the latest release from [GitHub Releases](https://github.com/uaiblaine/moodle-block_grade_me/releases).
2. Extract the archive into `blocks/grade_me`.

Then visit **Site administration > Notifications** to complete the installation.

## Configuration

Site administration > Plugins > Blocks > Grade Me

| Setting | Description |
|---------|-------------|
| Enable assign | Toggle assignment grading items |
| Enable quiz | Toggle quiz essay grading items |
| Enable forum | Toggle forum rating items |
| Enable glossary | Toggle glossary rating items |
| Enable data | Toggle database rating items |
| Enable lesson | Toggle lesson essay items |
| Admin view all | Allow site admins to view all courses |
| Max courses | Maximum number of courses to display |
| Max age (days) | Only show submissions within this many days (0 = unlimited) |

## Architecture

### Performance optimizations (v4.3.0)

The plugin has been significantly refactored for large-scale Moodle installations:

- **SQL subquery filtering** — Replaces array-based user ID filtering (`get_in_or_equal`) with SQL subqueries, eliminating the PostgreSQL 65,535 bind-parameter limit on courses with high enrollment.
- **Batched MERGE upserts** — On PostgreSQL 15+, grade item cache updates use SQL-standard `MERGE` statements with up to 500 rows per statement, reducing database roundtrips from N to 1 per course.
- **3-tier upsert strategy** — Automatic engine detection: `MERGE` (PG 15+) → `INSERT ... ON CONFLICT` (PG < 15) → `SELECT + INSERT/UPDATE` (MySQL/MariaDB).
- **Bulk quiz ngrade** — Quiz essay grading data is populated with `INSERT ... ON CONFLICT DO NOTHING` (PG) or `LEFT JOIN` anti-pattern (MySQL) in a single statement per course.
- **Batch user lookups** — Rendering pre-caches all user records for a course's gradeables in a single query, eliminating N+1 patterns.
- **Composite and UNIQUE indexes** — Optimized index coverage for both cron and rendering queries.

### Cross-database compatibility

All database operations are centralized in `classes/db_helper.php`, which detects the database engine at runtime and selects the optimal strategy. PostgreSQL-native features are used when available, with full MySQL/MariaDB fallback.

## Development notes

- PHPUnit and Behat tests are available under `tests/`.
- CI uses reusable Moodle plugin workflows via [catalyst-moodle-workflows](https://github.com/catalyst/catalyst-moodle-workflows).
- For release automation, tags matching `v*` trigger the Moodle Plugin Release workflow.

## Contributing

Issues and pull requests are welcome:

https://github.com/uaiblaine/moodle-block_grade_me/issues
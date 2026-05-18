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
 * CLI: truncate the SLA tables and re-queue a full backfill.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'help'  => false,
        'force' => false,
    ],
    [
        'h' => 'help',
        'f' => 'force',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error("Unrecognised options:\n  $unrecognized");
}

if ($options['help']) {
    cli_writeln(<<<HELP
Reset the block_grade_me SLA tables and re-queue a full backfill.

Usage:
    php blocks/grade_me/cli/reset_sla.php [--force]

Options:
    -h, --help   Show this help.
    -f, --force  Skip the confirmation prompt.

After running, the sla_backfill scheduled task will rebuild the ledger and
rollups on the next cron tick. The block will start serving correct numbers
once both sla_backfill and sla_drain have completed at least one cycle.
HELP);
    exit(0);
}

if (empty($options['force'])) {
    cli_writeln("This will TRUNCATE the following tables:");
    cli_writeln("  - block_grade_me_sla_sub");
    cli_writeln("  - block_grade_me_sla_group");
    cli_writeln("  - block_grade_me_sla_trend");
    cli_writeln("  - block_grade_me_sla_queue");
    $answer = cli_input("Type 'yes' to continue:", '');
    if (strtolower(trim($answer)) !== 'yes') {
        cli_writeln('Aborted.');
        exit(1);
    }
}

global $DB;
$DB->delete_records('block_grade_me_sla_sub');
$DB->delete_records('block_grade_me_sla_group');
$DB->delete_records('block_grade_me_sla_trend');
$DB->delete_records('block_grade_me_sla_site');
$DB->delete_records('block_grade_me_sla_queue');

set_config('sla_backfill_cursor', 0, 'block_grade_me');
set_config('sla_backfill_active', 1, 'block_grade_me');

\cache_helper::purge_by_definition('block_grade_me', 'responsiveness_payload');
\cache_helper::purge_by_definition('block_grade_me', 'gradeable_count');

cli_writeln('SLA tables truncated.');
cli_writeln('Backfill enabled; it will run on the next cron tick.');
exit(0);

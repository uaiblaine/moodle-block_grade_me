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
 * SLA bucket classification.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\local\sla;

defined('MOODLE_INTERNAL') || die();

/**
 * Maps waiting-time-in-hours to a bucket id and exposes the colour / lang key for that bucket.
 *
 * The boundaries are strict-less-than against an ascending list of thresholds
 * (excellent_max, good_max, regular_max). Anything at or above regular_max is critical.
 */
class bucket {
    /** @var string Bucket ids — kept in lockstep with atoms.jsx SLA_BUCKETS. */
    public const EXCELLENT = 'excellent';
    public const GOOD = 'good';
    public const REGULAR = 'regular';
    public const CRITICAL = 'critical';

    /**
     * Default thresholds when the admin setting is unset or malformed.
     *
     * @return int[] [excellent_max, good_max, regular_max] in hours
     */
    public static function default_thresholds(): array {
        return [24, 48, 120];
    }

    /**
     * Parse the admin setting "24,48,120" into three ascending positive ints.
     * Falls back to {@see self::default_thresholds()} on any parsing problem.
     *
     * @param string|null $csv
     * @return int[]
     */
    public static function parse_thresholds(?string $csv): array {
        if ($csv === null || trim($csv) === '') {
            return self::default_thresholds();
        }
        $parts = array_map('trim', explode(',', $csv));
        if (count($parts) !== 3) {
            return self::default_thresholds();
        }
        $ints = [];
        foreach ($parts as $part) {
            if (!ctype_digit($part) || (int) $part <= 0) {
                return self::default_thresholds();
            }
            $ints[] = (int) $part;
        }
        if (!($ints[0] < $ints[1] && $ints[1] < $ints[2])) {
            return self::default_thresholds();
        }
        return $ints;
    }

    /**
     * Bucket id for a waiting-hour value.
     *
     * A null input means "no pending work" (e.g. the group has zero submissions
     * waiting); treat that as EXCELLENT so the card reads "all clear" rather
     * than misleadingly showing a Critical chip on an empty state.
     *
     * @param float|null $hours Wait time in hours, or null when there is no pending work.
     * @param int[]|null $thresholds [excellent_max, good_max, regular_max]; default if null.
     * @return string One of EXCELLENT|GOOD|REGULAR|CRITICAL
     */
    public static function bucket_for(?float $hours, ?array $thresholds = null): string {
        if ($hours === null) {
            return self::EXCELLENT;
        }
        $thresholds = $thresholds ?? self::default_thresholds();
        if ($hours < $thresholds[0]) {
            return self::EXCELLENT;
        }
        if ($hours < $thresholds[1]) {
            return self::GOOD;
        }
        if ($hours < $thresholds[2]) {
            return self::REGULAR;
        }
        return self::CRITICAL;
    }

    /**
     * Unix timestamp before which submissions are ignored by every SLA query
     * (rollup, WS, dashboard, report) — mirrors the existing
     * `block_grade_me_maxage` semantic on the activity list.
     *
     * Returns null when the setting is 0 (the install default), meaning "no
     * age limit; consider every submission".
     *
     * Note: when the admin changes maxage, existing rollup rows do not
     * automatically refresh. Running the Reset SLA action enqueues a full
     * backfill so the rollups align with the new cutoff.
     *
     * @return int|null
     */
    public static function maxage_cutoff(): ?int {
        $maxage = (int) (get_config(null, 'block_grade_me_maxage') ?: 0);
        if ($maxage <= 0) {
            return null;
        }
        return time() - $maxage * DAYSECS;
    }

    /**
     * Whether SLA aggregations should include hidden courses. Mirrors the
     * existing `block_grade_me_enableshowhidden` setting that controls the
     * activity list. Default false (hidden courses are filtered out of
     * rollups + dashboard + block surfaces).
     *
     * @return bool
     */
    public static function include_hidden_courses(): bool {
        return (bool) get_config(null, 'block_grade_me_enableshowhidden');
    }

    /**
     * Whether SLA queries should include hidden course modules
     * (`cm.visible = 0`). Independent of {@see self::include_hidden_courses()};
     * a hidden activity inside a visible course is still filtered when this
     * returns false. Default false.
     *
     * @return bool
     */
    public static function include_hidden_activities(): bool {
        return (bool) get_config(null, 'block_grade_me_enableshowhiddenactivities');
    }

    /**
     * Whether the current user is a site admin AND has the
     * `block_grade_me_enableadminviewall` toggle on. When true, SLA surfaces
     * expand from "my groups" to "every visible group in every visible course"
     * to match the activity-list behaviour for admins.
     *
     * @return bool
     */
    public static function admin_views_all(): bool {
        return is_siteadmin() && !empty(get_config(null, 'block_grade_me_enableadminviewall'));
    }

    /**
     * Hex colour used for a bucket. Mirrors `SLA_BUCKETS` in atoms.jsx so the
     * SVG atoms render identically on the server (pages) and the client (block).
     *
     * @param string $bucket
     * @return string
     */
    public static function bucket_color(string $bucket): string {
        $map = [
            self::EXCELLENT => '#10b981',
            self::GOOD      => '#f59e0b',
            self::REGULAR   => '#f97316',
            self::CRITICAL  => '#ef4444',
        ];
        return $map[$bucket] ?? '#a8a29e';
    }

    /**
     * Lang string key for the bucket label.
     *
     * @param string $bucket
     * @return string Key for get_string($key, 'block_grade_me')
     */
    public static function bucket_label_key(string $bucket): string {
        $map = [
            self::EXCELLENT => 'bucket_excellent',
            self::GOOD      => 'bucket_good',
            self::REGULAR   => 'bucket_regular',
            self::CRITICAL  => 'bucket_critical',
        ];
        return $map[$bucket] ?? 'bucket_critical';
    }
}

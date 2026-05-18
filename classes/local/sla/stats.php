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
 * Stat helpers (median, percentile, max).
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_grade_me\local\sla;

defined('MOODLE_INTERNAL') || die();

/**
 * Pure stat helpers used by the SLA rollup service. Kept in PHP rather than SQL
 * so behaviour is identical across DB engines (some lack PERCENTILE_CONT).
 */
class stats {
    /**
     * Median of a numeric list. Returns null on an empty list.
     *
     * @param array $values
     * @return float|null
     */
    public static function median(array $values): ?float {
        if (empty($values)) {
            return null;
        }
        $values = array_values($values);
        sort($values, SORT_NUMERIC);
        $n = count($values);
        $mid = (int) ($n / 2);
        if ($n % 2 === 1) {
            return (float) $values[$mid];
        }
        return ((float) $values[$mid - 1] + (float) $values[$mid]) / 2.0;
    }

    /**
     * Linear-interpolated percentile (matches NumPy's "linear" / Excel PERCENTILE.INC).
     *
     * @param array $values
     * @param float $p Percentile in [0, 100]
     * @return float|null
     */
    public static function percentile(array $values, float $p): ?float {
        if (empty($values)) {
            return null;
        }
        $values = array_values($values);
        sort($values, SORT_NUMERIC);
        $n = count($values);
        if ($n === 1) {
            return (float) $values[0];
        }
        $p = max(0.0, min(100.0, $p));
        $rank = ($p / 100.0) * ($n - 1);
        $lo = (int) floor($rank);
        $hi = (int) ceil($rank);
        if ($lo === $hi) {
            return (float) $values[$lo];
        }
        $frac = $rank - $lo;
        return (float) $values[$lo] + $frac * ((float) $values[$hi] - (float) $values[$lo]);
    }

    /**
     * Max of a numeric list. Named max_value to avoid colliding with PHP's max().
     *
     * @param array $values
     * @return float|null
     */
    public static function max_value(array $values): ?float {
        if (empty($values)) {
            return null;
        }
        return (float) max($values);
    }
}

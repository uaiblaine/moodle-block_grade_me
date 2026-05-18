// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * SLA bucket classifier — JS mirror of classes/local/sla/bucket.php so the
 * AMD-rendered group card and the server-rendered pages agree on colours.
 *
 * @module     block_grade_me/bucket
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const BUCKETS = [
    {id: 'excellent', color: '#10b981'},
    {id: 'good',      color: '#f59e0b'},
    {id: 'regular',   color: '#f97316'},
    {id: 'critical',  color: '#ef4444'},
];

export const DEFAULT_THRESHOLDS = [24, 48, 120];

/**
 * Bucket id for a waiting-hour value.
 *
 * @param {number|null} hours
 * @param {number[]} [thresholds] [excellent_max, good_max, regular_max]
 * @returns {{id: string, color: string}}
 */
export function bucketFor(hours, thresholds) {
    if (hours === null || hours === undefined || isNaN(hours)) {
        return BUCKETS[3];
    }
    const t = thresholds || DEFAULT_THRESHOLDS;
    if (hours < t[0]) {
        return BUCKETS[0];
    }
    if (hours < t[1]) {
        return BUCKETS[1];
    }
    if (hours < t[2]) {
        return BUCKETS[2];
    }
    return BUCKETS[3];
}

/**
 * Hex colour for a bucket id.
 *
 * @param {string} id
 * @returns {string}
 */
export function bucketColor(id) {
    const b = BUCKETS.find((x) => x.id === id);
    return b ? b.color : '#a8a29e';
}

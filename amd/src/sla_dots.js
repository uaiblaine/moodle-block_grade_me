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
 * Compact 4-dot SLA indicator. Ported from atoms.jsx:89-104.
 *
 * @module     block_grade_me/sla_dots
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BUCKETS, bucketFor} from 'block_grade_me/bucket';

/**
 * @param {object} props
 * @param {number} props.hours
 * @param {number} [props.size=6]
 * @param {number} [props.gap=2]
 * @returns {string}
 */
export function markup(props) {
    const hours = props && props.hours;
    const size = (props && props.size) || 6;
    const gap = (props && props.gap) || 2;
    const bucket = bucketFor(hours);
    const idx = BUCKETS.findIndex((b) => b.id === bucket.id);

    const dots = BUCKETS.map((b, i) =>
        `<span style="width:${size}px;height:${size}px;border-radius:${size}px;`
        + `background:${i <= idx ? b.color : '#e7e5e4'}"></span>`
    ).join('');

    return `<span style="display:inline-flex;gap:${gap}px;align-items:center">${dots}</span>`;
}

/**
 * @param {HTMLElement} target
 * @param {object} props
 */
export function render(target, props) {
    target.innerHTML = markup(props);
}

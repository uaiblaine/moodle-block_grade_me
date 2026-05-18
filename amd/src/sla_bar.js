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
 * SLA bar SVG atom. Ported from atoms.jsx:46-87.
 *
 * @module     block_grade_me/sla_bar
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {bucketFor, DEFAULT_THRESHOLDS} from 'block_grade_me/bucket';

/**
 * @param {object} props
 * @param {number} props.hours
 * @param {number} [props.max=168]
 * @param {number} [props.height=8]
 * @param {boolean} [props.showGoal=true]
 * @param {number} [props.goalAt=24]
 * @param {number[]} [props.thresholds=[24,48,120]]
 * @returns {string}
 */
export function markup(props) {
    const hours = props && props.hours !== undefined ? Number(props.hours) || 0 : 0;
    const max = (props && props.max) || 168;
    const height = (props && props.height) || 8;
    const showGoal = !(props && props.showGoal === false);
    const goalAt = (props && props.goalAt !== undefined) ? props.goalAt : 24;
    const thresholds = (props && props.thresholds) || DEFAULT_THRESHOLDS;
    const bucket = bucketFor(hours, thresholds);
    const clamped = Math.min(hours, max);
    const pos = (clamped / max) * 100;
    const goalPos = (goalAt / max) * 100;
    const radius = height / 2;

    const stops = [
        {from: 0,                          to: (thresholds[0] / max) * 100, color: '#10b981'},
        {from: (thresholds[0] / max) * 100, to: (thresholds[1] / max) * 100, color: '#f59e0b'},
        {from: (thresholds[1] / max) * 100, to: (thresholds[2] / max) * 100, color: '#f97316'},
        {from: (thresholds[2] / max) * 100, to: 100,                         color: '#ef4444'},
    ];

    const zones = stops.map((s) =>
        `<div style="width:${s.to - s.from}%;background:${s.color};opacity:0.22"></div>`
    ).join('');

    const goal = showGoal
        ? `<div style="position:absolute;left:${goalPos}%;top:-2px;bottom:-2px;width:0;`
            + `border-left:1.5px dashed rgba(0,0,0,0.35)"></div>`
        : '';

    return `<div style="position:relative;width:100%;height:${height}px;`
        + `border-radius:${radius}px;overflow:visible">`
        + `<div style="position:absolute;inset:0;border-radius:${radius}px;`
        + `overflow:hidden;display:flex">${zones}</div>`
        + `<div style="position:absolute;left:0;top:0;height:100%;width:${pos}%;`
        + `background:${bucket.color};border-radius:${radius}px;transition:width .3s"></div>`
        + goal
        + `<div style="position:absolute;left:calc(${pos}% - 6px);top:-2px;`
        + `width:12px;height:${height + 4}px;border-radius:6px;background:#fff;`
        + `border:2px solid ${bucket.color};box-shadow:0 1px 3px rgba(0,0,0,.15)"></div>`
        + `</div>`;
}

/**
 * @param {HTMLElement} target
 * @param {object} props
 */
export function render(target, props) {
    target.innerHTML = markup(props);
}

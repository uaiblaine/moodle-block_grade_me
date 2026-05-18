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
 * Trend chip — coloured arrow + percentage. Ported from atoms.jsx:25-44.
 *
 * @module     block_grade_me/trend_chip
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @param {object} props
 * @param {number} props.pct Percentage delta vs previous period.
 * @param {boolean} [props.inverse=true] When true, a drop is good
 *   (median waiting decreasing). When false, a drop is bad.
 * @returns {string}
 */
export function markup(props) {
    const pct = (props && props.pct !== undefined) ? Number(props.pct) : 0;
    const inverse = !(props && props.inverse === false);

    const isDown = pct < 0;
    const isFlat = Math.abs(pct) < 2;
    const good = inverse ? isDown : !isDown;

    const color = isFlat ? '#78716c' : (good ? '#047857' : '#b91c1c');
    const bg = isFlat ? '#f5f5f4' : (good ? '#d1fae5' : '#fee2e2');
    const arrow = isFlat ? '→' : (isDown ? '↓' : '↑');

    return `<span style="display:inline-flex;align-items:center;gap:2px;`
        + `font-family:'JetBrains Mono',monospace;font-size:10px;font-weight:600;`
        + `color:${color};background:${bg};padding:2px 5px;border-radius:4px;`
        + `line-height:1">`
        + `<span style="font-size:10px">${arrow}</span>`
        + `<span>${Math.abs(pct)}%</span>`
        + `</span>`;
}

/**
 * @param {HTMLElement} target
 * @param {object} props
 */
export function render(target, props) {
    target.innerHTML = markup(props);
}

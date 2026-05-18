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
 * Sparkline SVG atom. Ported from claude-design-block_grade_me atoms.jsx:6-23.
 *
 * @module     block_grade_me/sparkline
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Build the SVG markup for a sparkline.
 *
 * @param {object} props
 * @param {number[]} props.data
 * @param {number} [props.width=80]
 * @param {number} [props.height=22]
 * @param {string} [props.color='#10b981']
 * @param {string} [props.fill='rgba(16,185,129,0.14)']
 * @param {number} [props.strokeWidth=1.5]
 * @param {boolean} [props.dot=true]
 * @returns {string}
 */
export function markup(props) {
    const data = (props && props.data) || [];
    const width = (props && props.width) || 80;
    const height = (props && props.height) || 22;
    const color = (props && props.color) || '#10b981';
    const fill = (props && props.fill) || 'rgba(16,185,129,0.14)';
    const strokeWidth = (props && props.strokeWidth !== undefined) ? props.strokeWidth : 1.5;
    const dot = !(props && props.dot === false);

    if (!data.length) {
        return '';
    }
    const min = Math.min.apply(null, data);
    const max = Math.max.apply(null, data);
    const range = (max - min) || 1;
    const stepX = data.length > 1 ? width / (data.length - 1) : 0;
    const points = data.map((v, i) => [i * stepX, height - ((v - min) / range) * (height - 4) - 2]);
    const path = points.map(([x, y], i) => (i === 0 ? `M${x},${y}` : `L${x},${y}`)).join(' ');
    const area = `${path} L${width},${height} L0,${height} Z`;
    const last = points[points.length - 1];

    return `<svg width="${width}" height="${height}" style="display:block" aria-hidden="true">`
        + `<path d="${area}" fill="${fill}"></path>`
        + `<path d="${path}" fill="none" stroke="${color}" stroke-width="${strokeWidth}" `
        + `stroke-linejoin="round" stroke-linecap="round"></path>`
        + (dot ? `<circle cx="${last[0]}" cy="${last[1]}" r="2" fill="${color}"></circle>` : '')
        + `</svg>`;
}

/**
 * Render directly into a host element.
 *
 * @param {HTMLElement} target
 * @param {object} props
 */
export function render(target, props) {
    target.innerHTML = markup(props);
}

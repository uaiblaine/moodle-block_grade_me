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
 * Mini timeline showing open → close window for an activity, with a
 * "today" marker. Ported from atoms.jsx:172-215.
 *
 * @module     block_grade_me/timeline_bar
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const URGENCY_COLORS = {
    'on-track': {fill: '#10b981', track: '#d1fae5'},
    'soon':     {fill: '#f59e0b', track: '#fef3c7'},
    'urgent':   {fill: '#f97316', track: '#ffedd5'},
    'overdue':  {fill: '#ef4444', track: '#fee2e2'},
};

const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[c]));

const fmtDate = (d) => {
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    return `${day}/${month}`;
};

/**
 * @param {object} props
 * @param {number|null} props.opens unix timestamp (seconds)
 * @param {number|null} props.closes unix timestamp (seconds)
 * @param {string} [props.urgency='on-track']
 * @param {number} [props.today] unix timestamp (seconds); defaults to Date.now
 * @param {boolean} [props.hasRule=true]
 * @param {string} [props.noRuleLabel='no rule']
 * @param {number} [props.height=4] track height in px
 * @param {number} [props.fontSize=9.5] date label font size in px
 * @returns {string}
 */
export function markup(props) {
    const opens = props && props.opens;
    const closes = props && props.closes;
    const urgency = (props && props.urgency) || 'on-track';
    const hasRule = !(props && props.hasRule === false);
    const noRuleLabel = (props && props.noRuleLabel) || 'no rule';
    const height = (props && props.height) || 4;
    const fontSize = (props && props.fontSize) || 9.5;

    if (!hasRule || !opens || !closes) {
        return `<div style="display:inline-flex;align-items:center;gap:4px;`
            + `padding:1px 5px;border-radius:3px;background:#f5f5f4;color:#a8a29e;`
            + `font-size:${fontSize}px;font-weight:600;font-family:'JetBrains Mono',monospace;`
            + `letter-spacing:0.2px;text-transform:uppercase">`
            + `<span style="width:5px;height:5px;border-radius:5px;background:#d6d3d1"></span>`
            + esc(noRuleLabel)
            + `</div>`;
    }

    const today = (props && props.today) || Math.floor(Date.now() / 1000);
    const o = new Date(opens * 1000);
    const c = new Date(closes * 1000);
    const t = new Date(today * 1000);
    const total = c - o;
    const elapsed = Math.max(0, Math.min(total, t - o));
    const pct = total > 0 ? (elapsed / total) * 100 : 0;
    const clamped = Math.min(100, Math.max(0, pct));
    const colors = URGENCY_COLORS[urgency] || URGENCY_COLORS['on-track'];
    const isOverdue = urgency === 'overdue';

    const radius = height / 2;
    const markerHeight = height + 6;
    const markerWidth = Math.max(7, Math.round(height * 1.3));
    const markerTop = -((markerHeight - height) / 2);

    return `<div style="display:flex;align-items:center;gap:8px;`
        + `font-family:'JetBrains Mono',monospace;font-size:${fontSize}px;color:#57534e">`
        + `<span>${fmtDate(o)}</span>`
        + `<div style="flex:1;position:relative;height:${height}px;background:${colors.track};`
        + `border-radius:${radius}px;overflow:visible">`
        + `<div style="position:absolute;left:0;top:0;bottom:0;width:${clamped}%;`
        + `background:${colors.fill};border-radius:${radius}px"></div>`
        + `<div style="position:absolute;left:calc(${clamped}% - ${markerWidth / 2}px);`
        + `top:${markerTop}px;width:${markerWidth}px;height:${markerHeight}px;`
        + `border-radius:${Math.round(radius * 1.5)}px;background:${colors.fill};`
        + `box-shadow:0 0 0 1.5px #fff"></div>`
        + `</div>`
        + `<span style="color:${isOverdue ? '#b91c1c' : '#57534e'};`
        + `font-weight:${isOverdue ? '700' : '400'}">${fmtDate(c)}</span>`
        + `</div>`;
}

/**
 * @param {HTMLElement} target
 * @param {object} props
 */
export function render(target, props) {
    target.innerHTML = markup(props);
}

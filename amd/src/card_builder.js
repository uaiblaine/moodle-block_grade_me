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
 * Composes the markup for one Grading Responsiveness group card from the
 * structured payload returned by block_grade_me_get_responsiveness.
 *
 * @module     block_grade_me/card_builder
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Sparkline from 'block_grade_me/sparkline';
import * as SlaBar from 'block_grade_me/sla_bar';
import * as TimelineBar from 'block_grade_me/timeline_bar';
import * as TrendChip from 'block_grade_me/trend_chip';
import {bucketColor} from 'block_grade_me/bucket';

const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[c]));

/**
 * Format a number of hours for the hero / tile displays.
 *
 * @param {number|null} h
 * @returns {string}
 */
function fmtHours(h) {
    if (h === null || h === undefined || isNaN(h)) {
        return '—';
    }
    if (h < 10) {
        return (Math.round(h * 10) / 10) + 'h';
    }
    return Math.round(h) + 'h';
}

/**
 * Return a bucket chip (label + soft background in the bucket colour).
 *
 * @param {string} bucket
 * @param {string} label
 * @returns {string}
 */
function bucketChip(bucket, label) {
    const color = bucketColor(bucket);
    return `<span style="font-size:10px;font-weight:600;padding:2px 6px;`
        + `border-radius:4px;background:${color}1f;color:${color}">${esc(label)}</span>`;
}

/**
 * Build the complete card markup for one group.
 *
 * @param {object} group WS payload for one group (see get_responsiveness shape).
 * @param {object} strings map of i18n strings used in the card.
 * @returns {string} HTML
 */
export function buildGroupCard(group, strings) {
    const bucket = group.bucket;
    const bucketHex = bucketColor(bucket);

    const header = `<button type="button" class="grademe-group-toggle" `
        + `data-groupid="${group.groupid}" aria-expanded="true" `
        + `style="display:flex;align-items:center;gap:8px;width:100%;background:none;`
        + `border:none;padding:10px 12px;cursor:pointer;text-align:left;`
        + `border-bottom:1px solid #e7e5e4;font-family:Manrope,system-ui,sans-serif">`
        + `<span class="grademe-chevron" style="display:inline-block;transition:transform .15s">▾</span>`
        + `<div style="flex:1;min-width:0">`
        + `<div style="font-size:10px;font-weight:600;color:#a8a29e;text-transform:uppercase;`
        + `letter-spacing:0.4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" `
        + `title="${esc(group.coursename)}">${esc(group.coursename)}</div>`
        + `<div style="font-size:13px;font-weight:700;color:#1c1917;white-space:nowrap;`
        + `overflow:hidden;text-overflow:ellipsis" title="${esc(group.groupname)}">`
        + `${esc(group.groupname)}</div>`
        + `</div>`
        + bucketChip(bucket, strings['bucket_' + bucket] || bucket)
        + `</button>`;

    const hero = `<div style="padding:14px 12px 6px 12px">`
        + `<div style="display:flex;align-items:baseline;gap:6px">`
        + `<span style="font-family:'JetBrains Mono',ui-monospace,monospace;font-size:36px;`
        + `font-weight:700;color:${bucketHex};font-feature-settings:'tnum'">`
        + `${esc(fmtHours(group.median_h))}</span>`
        + `</div>`
        + `<div style="font-size:10px;color:#78716c;text-transform:uppercase;`
        + `letter-spacing:0.4px;font-weight:600">${esc(strings.typical_response || 'Typical response')}</div>`
        + `</div>`;

    const slaBar = `<div style="padding:0 12px 4px 12px">`
        + SlaBar.markup({hours: group.median_h || 0, max: 168, goalAt: 24})
        + `</div>`
        + `<div style="padding:0 12px 10px 12px;display:flex;justify-content:space-between;`
        + `font-family:'JetBrains Mono',monospace;font-size:9px;color:#a8a29e">`
        + `<span>0h</span><span>24h</span><span>48h</span><span>120h+</span>`
        + `</div>`;

    const trend = `<div style="margin:0 12px 10px 12px;padding:8px 10px;background:#f5f5f4;`
        + `border-radius:8px;display:flex;align-items:center;justify-content:space-between;gap:10px">`
        + `<div style="display:flex;flex-direction:column;gap:2px">`
        + `<span style="font-size:9px;font-weight:600;color:#78716c;text-transform:uppercase;`
        + `letter-spacing:0.3px">${esc(strings.last_30_days || 'Last 30 days')}</span>`
        + TrendChip.markup({pct: Math.round(group.trend_pct || 0), inverse: true})
        + `</div>`
        + `<div>${Sparkline.markup({data: group.trend_series || [], width: 88, height: 26, color: bucketHex})}</div>`
        + `</div>`;

    const criticalAccent = group.critical > 0;
    const tiles = `<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;padding:0 12px 10px 12px">`
        + `<a href="${esc(group.pending_url || '#')}" style="text-decoration:none;display:block;`
        + `padding:8px 10px;border:1px solid #e7e5e4;border-radius:8px;background:#fafaf9;`
        + `color:#1c1917">`
        + `<div style="font-size:9.5px;font-weight:600;color:#78716c;text-transform:uppercase;`
        + `letter-spacing:0.3px">${esc(strings.pending || 'Pending')}</div>`
        + `<div style="font-family:'JetBrains Mono',monospace;font-size:20px;font-weight:700;`
        + `font-feature-settings:'tnum'">${group.pending}</div>`
        + `</a>`
        + `<a href="${esc(group.critical_url || '#')}" style="text-decoration:none;display:block;`
        + `padding:8px 10px;border:1px solid ${criticalAccent ? '#fecaca' : '#e7e5e4'};`
        + `border-radius:8px;background:${criticalAccent ? '#fef2f2' : '#fafaf9'};`
        + `color:${criticalAccent ? '#b91c1c' : '#1c1917'}">`
        + `<div style="font-size:9.5px;font-weight:600;color:${criticalAccent ? '#b91c1c' : '#78716c'};`
        + `text-transform:uppercase;letter-spacing:0.3px">${esc(strings.critical || 'Critical')}</div>`
        + `<div style="font-family:'JetBrains Mono',monospace;font-size:20px;font-weight:700;`
        + `font-feature-settings:'tnum'">${group.critical}</div>`
        + `</a>`
        + `</div>`;

    const compareCol = group.compare
        ? `<div style="text-align:right">`
            + `<div style="color:#78716c;font-weight:600;text-transform:uppercase;`
            + `letter-spacing:0.3px;font-size:9px">${esc(strings.school_median || 'School')}</div>`
            + `<div style="font-family:'JetBrains Mono',monospace;font-weight:700">`
            + `${esc(fmtHours(group.compare.school_median))}</div>`
            + `</div>`
        : '';

    const percentile = `<div style="display:flex;justify-content:space-between;`
        + `padding:8px 12px;border-top:1px solid #f5f5f4;font-size:10px">`
        + `<div style="text-align:left">`
        + `<div style="color:#78716c;font-weight:600;text-transform:uppercase;`
        + `letter-spacing:0.3px;font-size:9px">${esc(strings.within_90 || '90% within')}</div>`
        + `<div style="font-family:'JetBrains Mono',monospace;font-weight:700">`
        + `${esc(fmtHours(group.p90_h))}</div>`
        + `</div>`
        + `<div style="text-align:center">`
        + `<div style="color:#78716c;font-weight:600;text-transform:uppercase;`
        + `letter-spacing:0.3px;font-size:9px">${esc(strings.longest_wait || 'Longest')}</div>`
        + `<div style="font-family:'JetBrains Mono',monospace;font-weight:700">`
        + `${esc(fmtHours(group.max_h))}</div>`
        + `</div>`
        + compareCol
        + `</div>`;

    let compareBar = '';
    if (group.compare && group.compare.school_median > 0) {
        const denom = group.compare.school_median * 2;
        const yourPct = Math.min(100, Math.max(0, ((group.median_h || 0) / denom) * 100));
        const top10Pct = Math.min(100, Math.max(0, ((group.compare.top10 || 0) / denom) * 100));
        compareBar = `<div style="padding:6px 12px 10px 12px">`
            + `<div style="position:relative;height:6px;background:#f5f5f4;border-radius:3px">`
            + `<span title="${esc(strings.compare_top10 || 'Top 10%')}" `
            + `style="position:absolute;left:${top10Pct}%;top:-3px;width:12px;height:12px;`
            + `border-radius:6px;background:#1c1917"></span>`
            + `<span title="${esc(strings.compare_you || 'You')}" `
            + `style="position:absolute;left:${yourPct}%;top:-3px;width:12px;height:12px;`
            + `border-radius:6px;background:#e11d48"></span>`
            + `</div>`
            + `</div>`;
    }

    let activities = '';
    if (group.activities && group.activities.length) {
        const items = group.activities.map((a) => {
            const overrideUrl = a.override_url || '#';
            const ruleLabel = a.has_rule
                ? (strings.rule || 'RULE').toUpperCase()
                : (strings.no_rule || 'NO RULE').toUpperCase();
            const ruleBadgeStyle = a.has_rule
                ? 'background:#d1fae5;color:#047857;border:1px solid #a7f3d0'
                : 'background:transparent;color:#a8a29e;border:1px dashed #d6d3d1';
            const ruleBadge = `<a href="${esc(overrideUrl)}" `
                + `title="${esc(strings.open_dashboard || '')}" `
                + `style="font-size:9px;font-weight:700;padding:3px 8px;border-radius:4px;`
                + `text-transform:uppercase;letter-spacing:0.3px;text-decoration:none;`
                + `flex:0 0 auto;${ruleBadgeStyle}">${esc(ruleLabel)}</a>`;

            const body = a.has_rule
                ? TimelineBar.markup({
                    opens: a.opens_at,
                    closes: a.closes_at,
                    urgency: a.urgency,
                    hasRule: true,
                    height: 7,
                    fontSize: 11,
                })
                : `<div style="display:inline-flex;align-items:center;gap:6px;`
                    + `color:#a8a29e;font-family:'JetBrains Mono',monospace;font-size:11px;`
                    + `font-weight:600;text-transform:uppercase;letter-spacing:0.3px">`
                    + `<span style="width:6px;height:6px;border-radius:6px;background:#d6d3d1"></span>`
                    + esc(strings.no_rule || 'NO RULE').toUpperCase()
                    + `</div>`;

            return `<article style="border:1px solid #e7e5e4;border-radius:8px;`
                + `padding:10px 12px;margin-bottom:8px;background:#fff">`
                + `<div style="display:flex;align-items:flex-start;justify-content:space-between;`
                + `gap:8px;margin-bottom:8px">`
                + `<span style="flex:1;min-width:0;font-size:14px;font-weight:600;color:#1c1917;`
                + `line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" `
                + `title="${esc(a.name)}">${esc(a.name)}</span>`
                + ruleBadge
                + `</div>`
                + body
                + `</article>`;
        }).join('');
        activities = `<div style="border-top:1px solid #f5f5f4;padding:10px 12px;background:#fafaf9">`
            + `<div style="font-size:10px;font-weight:700;color:#78716c;`
            + `text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">`
            + esc(strings.activities_open_close || 'Activities · open/close rule')
            + `</div>`
            + items
            + `</div>`;
    }

    return `<section data-groupid="${group.groupid}" `
        + `style="border:1px solid #e7e5e4;border-radius:10px;background:#fff;`
        + `margin-bottom:10px;overflow:hidden;font-family:Manrope,system-ui,sans-serif">`
        + header
        + `<div class="grademe-group-body">`
        + hero
        + slaBar
        + trend
        + tiles
        + percentile
        + compareBar
        + activities
        + `</div>`
        + `</section>`;
}

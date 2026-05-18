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
 * Responsiveness section bootstrap. Calls block_grade_me_get_responsiveness
 * once per page load, composes group cards via card_builder, and attaches
 * collapse handlers.
 *
 * @module     block_grade_me/responsiveness
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import * as CardBuilder from 'block_grade_me/card_builder';

const SECTION_ID = 'grademe-responsiveness';
const BODY_CLASS = 'grademe-responsiveness-body';

/**
 * Entry point invoked from the {{#js}} block of the skeleton template.
 *
 * @param {object} data {courseid, strings}
 */
export const init = (data) => {
    const section = document.getElementById(SECTION_ID);
    if (!section) {
        return;
    }
    const courseid = parseInt(section.getAttribute('data-courseid'), 10);
    const strings = (data && data.strings) || {};
    fetchAndRender(section, courseid, strings);
};

/**
 * Call the WS and render the result.
 *
 * @param {HTMLElement} section
 * @param {number} courseid
 * @param {object} strings
 */
function fetchAndRender(section, courseid, strings) {
    const body = section.querySelector('.' + BODY_CLASS);
    if (!body) {
        return;
    }
    const requests = Ajax.call([{
        methodname: 'block_grade_me_get_responsiveness',
        args: {courseid: courseid},
    }]);
    requests[0]
        .then((response) => {
            renderGroups(section, body, response, strings);
            section.setAttribute('aria-busy', 'false');
            return null;
        })
        .catch((err) => {
            body.innerHTML = '<div class="text-muted small">'
                + escapeHtml(strings.responsiveness_load_failed || 'Failed to load responsiveness data.')
                + '</div>';
            section.setAttribute('aria-busy', 'false');
            if (window.console) {
                window.console.error('block_grade_me: responsiveness fetch failed', err);
            }
        });
}

/**
 * Compose group cards into the body. Empty state when there are no groups.
 *
 * @param {HTMLElement} section
 * @param {HTMLElement} body
 * @param {object} response
 * @param {object} strings
 */
function renderGroups(section, body, response, strings) {
    if (!response.groups || !response.groups.length) {
        body.innerHTML = '<div class="text-muted small">'
            + escapeHtml(strings.responsiveness_no_groups || 'No groups to show.')
            + '</div>';
        return;
    }
    body.innerHTML = response.groups
        .map((group) => CardBuilder.buildGroupCard(group, strings))
        .join('');
    attachToggles(body);
}

/**
 * Attach collapse / expand handlers to each group card. Persists state in
 * a local in-memory map; user-preference persistence is a follow-up.
 *
 * @param {HTMLElement} body
 */
function attachToggles(body) {
    body.querySelectorAll('.grademe-group-toggle').forEach((btn) => {
        btn.addEventListener('click', () => {
            const expanded = btn.getAttribute('aria-expanded') === 'true';
            const section = btn.closest('section');
            if (!section) {
                return;
            }
            const groupBody = section.querySelector('.grademe-group-body');
            if (!groupBody) {
                return;
            }
            groupBody.style.display = expanded ? 'none' : '';
            btn.setAttribute('aria-expanded', String(!expanded));
            const chev = btn.querySelector('.grademe-chevron');
            if (chev) {
                chev.style.transform = expanded ? 'rotate(-90deg)' : '';
            }
        });
    });
}

/**
 * Minimal HTML escape for the few user-controlled strings we splice into innerHTML.
 *
 * @param {string} s
 * @returns {string}
 */
function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

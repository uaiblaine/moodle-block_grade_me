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
 * Asynchronous loader for the Grade Me block.
 *
 * Renders an empty skeleton instantly from the data passed by the PHP block,
 * then calls block_grade_me_get_gradeable_count once per (courseid, moduletype)
 * to populate badge counts.
 *
 * @module     block_grade_me/grademe
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {get_string as getString} from 'core/str';

const HIDE_CLASS = 'block_grade_me_hide';
const SPINNER_HTML =
    '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
const DEBOUNCE_MS = 100;

/**
 * Sleep helper used to debounce sequential requests within a course.
 *
 * @param {number} ms
 * @returns {Promise<void>}
 */
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/**
 * Escape a string for safe insertion into HTML attribute / text content.
 *
 * @param {string} s
 * @returns {string}
 */
const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[c]));

/**
 * Build the course/module DOM and append it to the existing skeleton root.
 *
 * @param {HTMLElement} list  The <dl id="grademe-courses-list"> element.
 * @param {object}      data  Skeleton data passed from PHP.
 */
const renderSkeleton = (list, data) => {
    const frag = document.createDocumentFragment();
    for (const course of data.courses) {
        const wrap = document.createElement('div');
        wrap.dataset.courseid = course.courseid;

        const dt = document.createElement('dt');
        dt.id = `courseid${course.courseid}`;
        dt.className = 'cmod';
        dt.innerHTML = `
            <div tabindex="0" role="button" class="toggle open fa fa-caret-right"
                 aria-expanded="true" data-action="grademe-toggle-course">
                <span class="sr-only">Toggle Section</span>
            </div>
            <a href="${esc(course.courseurl)}" class="grademe-course-name">${esc(course.coursename)}</a>
        `;
        wrap.appendChild(dt);

        for (const mt of course.moduletypes) {
            for (const inst of mt.instances) {
                const dd = document.createElement('dd');
                dd.id = `cmid${inst.cmid}`;
                dd.className = 'module';
                dd.dataset.moduletype = mt.moduletype;
                dd.dataset.cmid = inst.cmid;
                dd.innerHTML = `
                    <div class="dd-wrap">
                        <div tabindex="0" role="button" class="toggle fa fa-caret-right"
                             aria-expanded="false" data-action="grademe-toggle-module">
                            <span class="sr-only">Toggle Section</span>
                        </div>
                        <a href="${esc(inst.gradeurl)}" class="grademe-course-icon" title="${esc(inst.itemname)}">
                            <i class="fa fa-check-square-o" aria-hidden="true"></i>
                        </a>
                        <a href="${esc(inst.gradeurl)}" class="grademe-mod-name">${esc(inst.itemname)}</a>
                        <span class="badge badge-pill badge-primary"
                              id="badge-${esc(mt.moduletype)}-${inst.cmid}"
                              data-cmid="${inst.cmid}"
                              data-gradeurl="${esc(inst.gradeurl)}"
                              role="button"
                              tabindex="0"
                              aria-busy="true">${SPINNER_HTML}</span>
                    </div>
                    <ul class="gradable-list ${HIDE_CLASS}"></ul>
                `;
                wrap.appendChild(dd);
            }
        }
        frag.appendChild(wrap);
    }
    list.innerHTML = '';
    list.appendChild(frag);
    list.setAttribute('aria-busy', 'false');
};

/**
 * Wire toggle handlers (per-course, per-module, expand-all).
 *
 * @param {HTMLElement} root
 */
const wireToggles = (root) => {
    root.addEventListener('click', (ev) => {
        const target = ev.target.closest('[data-action]');
        if (!target) {
            return;
        }
        const action = target.dataset.action;

        if (action === 'grademe-toggle-all') {
            const list = root.querySelector('#grademe-courses-list');
            const expand = !list.classList.contains('expanded');
            list.classList.toggle('expanded', expand);
            root.querySelectorAll('.toggle').forEach((t) => t.classList.toggle('open', expand));
            root.querySelectorAll('dd').forEach((d) => d.classList.toggle(HIDE_CLASS, !expand));
            root.querySelectorAll('dd ul').forEach((u) => u.classList.toggle(HIDE_CLASS, !expand));
            target.setAttribute('aria-expanded', expand ? 'true' : 'false');
            return;
        }

        if (action === 'grademe-toggle-course') {
            const dt = target.closest('dt');
            if (!dt) {
                return;
            }
            const open = !target.classList.contains('open');
            target.classList.toggle('open', open);
            target.setAttribute('aria-expanded', open ? 'true' : 'false');
            let sibling = dt.nextElementSibling;
            while (sibling && sibling.tagName === 'DD') {
                sibling.classList.toggle(HIDE_CLASS, !open);
                sibling = sibling.nextElementSibling;
            }
            return;
        }

        if (action === 'grademe-toggle-module') {
            const dd = target.closest('dd');
            if (!dd) {
                return;
            }
            const open = !target.classList.contains('open');
            target.classList.toggle('open', open);
            target.setAttribute('aria-expanded', open ? 'true' : 'false');
            const ul = dd.querySelector('ul');
            if (ul) {
                ul.classList.toggle(HIDE_CLASS, !open);
            }
        }
    });

    // Keyboard activation for divs acting as buttons.
    root.addEventListener('keydown', (ev) => {
        if (ev.key !== 'Enter' && ev.key !== ' ') {
            return;
        }
        const target = ev.target.closest('[data-action]');
        if (target) {
            ev.preventDefault();
            target.click();
        }
    });

    // Badge click → navigate to the module-level grading screen.
    root.addEventListener('click', (ev) => {
        const badge = ev.target.closest('.badge[data-gradeurl]');
        if (badge) {
            window.location.assign(badge.dataset.gradeurl);
        }
    });
};

/**
 * Apply a successful WS response to the matching badges.
 *
 * @param {object} response
 */
const applyCounts = (response) => {
    const byCmid = new Map();
    for (const sub of response.submissions || []) {
        byCmid.set(sub.coursemoduleid, (byCmid.get(sub.coursemoduleid) || 0) + 1);
    }
    // Update only badges that exist for this moduletype.
    document.querySelectorAll(
        `dd[data-moduletype="${response.moduletype}"]`
    ).forEach((dd) => {
        const cmid = Number(dd.dataset.cmid);
        const badge = dd.querySelector('.badge');
        if (!badge) {
            return;
        }
        const count = byCmid.get(cmid) || 0;
        badge.textContent = String(count);
        badge.setAttribute('aria-busy', 'false');
        badge.setAttribute('aria-label', `${count}`);
    });
};

/**
 * Mark every badge for a (course, moduletype) as failed.
 *
 * @param {number} courseid
 * @param {string} moduletype
 */
const markFallback = async (courseid, moduletype) => {
    const label = await getString('badge_unknown', 'block_grade_me').catch(() => 'Unable to load count');
    document.querySelectorAll(
        `[data-courseid="${courseid}"] dd[data-moduletype="${moduletype}"] .badge`
    ).forEach((badge) => {
        badge.textContent = '?';
        badge.setAttribute('aria-busy', 'false');
        badge.setAttribute('aria-label', label);
        badge.setAttribute('title', label);
    });
};

/**
 * Fire one WS request per (course, moduletype). Courses run in parallel;
 * within each course, module-type calls are sequenced with a small debounce
 * so a single user does not fan out dozens of simultaneous requests.
 *
 * @param {object} data Skeleton data.
 */
const fetchCounts = (data) => {
    const coursePromises = data.courses.map(async (course) => {
        for (const mt of course.moduletypes) {
            await sleep(DEBOUNCE_MS);
            try {
                const [promise] = Ajax.call([{
                    methodname: 'block_grade_me_get_gradeable_count',
                    args: {courseid: course.courseid, moduletype: mt.moduletype},
                }]);
                const response = await promise;
                applyCounts(response);
            } catch (e) {
                window.console.warn('block_grade_me: failed to load counts', e);
                await markFallback(course.courseid, mt.moduletype);
            }
        }
    });
    return Promise.all(coursePromises);
};

/**
 * Entry point bootstrapped from PHP via $PAGE->requires->js_call_amd().
 *
 * @param {object} data Skeleton data ({courses, maxcourses, excessmessage}).
 */
export const init = (data) => {
    const root = document.getElementById('grademe-block-container');
    if (!root) {
        return;
    }
    const list = root.querySelector('#grademe-courses-list');
    if (!list) {
        return;
    }
    if (!data || !Array.isArray(data.courses) || data.courses.length === 0) {
        list.setAttribute('aria-busy', 'false');
        return;
    }
    renderSkeleton(list, data);
    wireToggles(root);
    fetchCounts(data);
};

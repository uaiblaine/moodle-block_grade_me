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
 * Detailed Report page enhancements. Submits the sort form on change and turns
 * the distribution bar segments into filter shortcuts.
 *
 * @module     block_grade_me/report
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @returns {URLSearchParams}
 */
function currentParams() {
    return new URLSearchParams(window.location.search);
}

/**
 * Update the page's filter and navigate.
 *
 * @param {string} bucket
 */
function jumpToBucket(bucket) {
    const params = currentParams();
    // Distribution bar segments only filter to critical or back to all/pending.
    // Anything not critical drops the user into "all" so the table still has rows.
    if (bucket === 'critical') {
        params.set('filter', 'critical');
    } else {
        params.set('filter', 'all');
    }
    params.set('page', '0');
    window.location.search = '?' + params.toString();
}

/**
 * Entry point invoked from the report template's {{#js}} block.
 */
export const init = () => {
    const select = document.getElementById('grademe-report-sort');
    if (select) {
        select.addEventListener('change', () => {
            const form = select.closest('form');
            if (form) {
                form.submit();
            }
        });
    }
    document.querySelectorAll('[data-distribution-bucket]').forEach((seg) => {
        seg.addEventListener('click', (ev) => {
            ev.preventDefault();
            const bucket = seg.getAttribute('data-distribution-bucket');
            if (bucket) {
                jumpToBucket(bucket);
            }
        });
    });
};

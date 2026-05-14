<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Grade Me block external web service function declarations.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_grade_me_get_gradeable_count' => [
        'classname'    => 'block_grade_me\\external\\get_gradeable_count',
        'methodname'   => 'execute',
        'description'  => 'Count and list ungraded items for one module type in a course.',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'mod/assign:grade,mod/quiz:grade,mod/forum:rate,mod/glossary:rate,mod/data:rate,mod/lesson:grade',
    ],
];

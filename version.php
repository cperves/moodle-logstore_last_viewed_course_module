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
 * last_viewed_course_module log store.
 *
 * @package    logstore_last_course_activities
 * @author Céline Pervès <cperves@unistra.fr>
 * @copyright Université de Strasbourg 2020 {@link http://unistra.fr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2023011900; // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires = 2022112801; // Requires this Moodle version.
$plugin->component = 'logstore_last_viewed_course_module'; // Full name of the plugin (used for diagnostics).

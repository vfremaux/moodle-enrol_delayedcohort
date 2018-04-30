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
 * Delayed Cohort enrolment plugin version specification.
 *
 * @package    enrol_delayedcohort
 * @category   enrol
 * @copyright  2015 Valery Fremaux (valery@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2015101703;        // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires  = 2017050500;        // Requires this Moodle version.
$plugin->component = 'enrol_delayedcohort';    // Full name of the plugin (used for diagnostics).
$plugin->release = '3.3.0 (Build 2015101703)';
$plugin->maturity = MATURITY_STABLE;

// Non Moodle attributes.
$plugin->codeincrement = '3.3.0000';
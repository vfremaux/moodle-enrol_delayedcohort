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
 * Guest enrolment external functions and service definitions.
 *
 * @package    enrol_deleayedcohort
 * @category   external
 * @copyright  2025 Valery Fremaux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$functions = [

    'enrol_delayedcohort_get_instances' => [
        'classname'   => 'enrol_delayedcohort_external',
        'methodname'  => 'get_instances',
        'classpath'   => 'enrol/delayedcohort/externallib.php',
        'description' => 'Get enrol instances in a course',
        'capabilities'=> 'enrol/delayedcohort:config',
        'type'        => 'read',
    ],

    'enrol_delayedcohort_get_instance_info' => [
        'classname'   => 'enrol_delayedcohort_external',
        'methodname'  => 'get_instance_info',
        'classpath'   => 'enrol/delayedcohort/externallib.php',
        'description' => 'Get info about enrol instances',
        'capabilities'=> 'enrol/delayedcohort:config',
        'type'        => 'read',
    ],

    'enrol_delayedcohort_add_method_to_course' => [
        'classname'   => 'enrol_delayedcohort_external',
        'methodname'  => 'add_method_to_course',
        'classpath'   => 'enrol/delayedcohort/externallib.php',
        'description' => 'Add a delayedcohort instance to course for a specific cohort',
        'type'        => 'read',
    ],

    'enrol_delayedcohort_delete_method_instance' => [
        'classname'   => 'enrol_delayedcohort_external',
        'methodname'  => 'delete_method_instance',
        'classpath'   => 'enrol/delayedcohort/externallib.php',
        'description' => 'Removes an instance of delayedcohort plugin',
        'type'        => 'read',
    ],

    'enrol_delayedcohort_get_enrollable_users' => [
        'classname'   => 'enrol_delayedcohort_external',
        'methodname'  => 'get_enrollable_users',
        'classpath'   => 'enrol/delayedcohort/externallib.php',
        'description' => 'Get users to be enrolled with enrol delayedcohort plugin',
        'type'        => 'read',
    ],
];

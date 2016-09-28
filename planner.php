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
 * Adds new instance of enrol_cohort to specified course.
 *
 * @package   enrol_delayedcohort
 * @category  enrol
 * @author    Valery Fremaux <valery.fremaux@gmail.com>
 * @copyright 2015 Valery Fremaux {@link http://www.mylearningfactory.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/enrol/delayedcohort/edit_form.php');
require_once($CFG->dirroot.'/enrol/delayedcohort/locallib.php');
require_once($CFG->dirroot.'/group/lib.php');

$context = context_system::instance();
$PAGE->set_context($context);
$view = optional_param('view', 'bycourse', PARAM_TEXT);
$action = optional_param('what', '', PARAM_TEXT);

// Security.

require_login();
require_capability('enrol/delayedcohort:plan', $context);
$renderer = $PAGE->get_renderer('enrol_delayedcohort');

$url = new moodle_url('/enrol/delayedcohort/planner.php', array('view' => $view));
$PAGE->set_url($url);
$PAGE->set_heading(get_string('pluginname', 'enrol_delayedcohort'));
$PAGE->set_pagelayout('base');
$PAGE->navbar->add(get_string('pluginname', 'enrol_delayedcohort'));

if ($action) {
    include($CFG->dirroot.'/enrol/delayedcohort/planner.controller.php');
}

if (!enrol_is_enabled('delayedcohort')) {
    echo $OUTPUT->header();
    echo $OUTPUT->box(get_string('notenabled', 'enrol_delayedcohort'));
    echo $OUTPUT->footer();
    die;
}

switch ($view) {
    case 'bycourse':
        include_once($CFG->dirroot.'/enrol/delayedcohort/planner_bycourse.php');
        break;

    case 'bycohort':
        include_once($CFG->dirroot.'/enrol/delayedcohort/planner_bycohort.php');
        break;

    case 'unassigned':
        include_once($CFG->dirroot.'/enrol/delayedcohort/planner_unassigned.php');
        break;

    default:
        print_error('badview', 'enrol_delayedcohort');
}

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
 * Adds new instance of enrol_delayedcohort to specified course.
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

$courseid = required_param('courseid', PARAM_INT);
$cohortid = optional_param('cohortid', 0, PARAM_INT);
$categoryid = optional_param('category', 0, PARAM_INT);
$instanceid = optional_param('id', 0, PARAM_INT);
$return = optional_param('return', '', PARAM_TEXT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

// Security.

require_login($course);
require_capability('moodle/course:enrolconfig', $context);
require_capability('enrol/delayedcohort:config', $context);

$PAGE->set_url('/enrol/delayedcohort/edit.php', ['courseid' => $course->id, 'id' => $instanceid]);
$PAGE->set_pagelayout('admin');

if (!empty($return)) {
    // Check security here as only site admin can use global planner.
    if (has_capability('moodle/site:config', context_system::instance())) {
        if (strpos($return, '_') !== false) {
            list($returnfoo, $view) = explode('_', $return);
            $returnurl = new moodle_url('/enrol/delayedcohort/planner.php', ['view' => $view, 'category' => $categoryid]);
        } else {
            $returnurl = new moodle_url('/enrol/delayedcohort/planner.php', ['category' => $categoryid]);
        }
    }
} else {
    $returnurl = new moodle_url('/enrol/instances.php', ['id' => $course->id]);
}

if (!enrol_is_enabled('delayedcohort')) {
    redirect($returnurl);
}

$enrol = enrol_get_plugin('delayedcohort');

if ($instanceid) {
    $params = ['courseid' => $course->id, 'enrol' => 'delayedcohort', 'id' => $instanceid];
    $instance = $DB->get_record('enrol', $params, '*', MUST_EXIST);

} else {
    // No instance yet, we have to add new instance.
    if (!$enrol->get_newinstance_link($course->id)) {
        redirect($returnurl);
    }
    navigation_node::override_active_url(new moodle_url('/enrol/instances.php', ['id' => $course->id]));
    $instance = new stdClass();
    $instance->id         = null;
    $instance->courseid   = $course->id;
    $instance->enrol      = 'delayedcohort';
    $instance->enrolstartdate = 0;  // start.
    $instance->enrolenddate = 0;  // end.
    $instance->customint1 = ''; // Cohort id.
    $instance->customint2 = 0;  // Optional group propagation.
    $instance->customint3 = 0;  // legal start.
    $instance->customint4 = 0;  // legal end.
    $instance->customchar1 = '';  // Group idnumber.
    $instance->customchar2 = '';  // Group name.
}

// Try and make the manage instances node on the navigation active.
$courseadmin = $PAGE->settingsnav->get('courseadmin');
if ($courseadmin && $courseadmin->get('users') && $courseadmin->get('users')->get('manageinstances')) {
    $courseadmin->get('users')->get('manageinstances')->make_active();
}

$mform = new enrol_delayedcohort_edit_form(null, [$instance, $enrol, $course]);

if ($mform->is_cancelled()) {
    redirect($returnurl);

} elseif ($data = $mform->get_data()) {
    if ($data->id) {
        // NOTE: no cohort changes here!!!
        if ($data->roleid != $instance->roleid) {
            // The sync script can only add roles, for perf reasons it does not modify them.
            $params = [
                'contextid' => $context->id,
                'roleid' => $instance->roleid,
                'component' => 'enrol_delayedcohort',
                'itemid' => $instance->id
            ];
            role_unassign_all($params);
        }
        $instance->name         = $data->name;
        $instance->status       = $data->status;
        $instance->roleid       = $data->roleid;
        $instance->enrolstartdate   = $data->enrolstartdate; // Trigger date.
        $instance->enrolenddate   = $data->enrolenddate; // End date.
        $instance->customint1   = $data->customint1; // Cohort id
        $instance->customint2   = $data->customint2; // Group propagation mode.
        $instance->customint3   = $data->customint3; // Legal start date.
        $instance->customint4   = $data->customint4; // Legal end date.
        $instance->customchar1 = $data->customchar1;  // Group idnumber.
        $instance->customchar2 = $data->customchar2;  // Group name.
        $instance->timemodified = time();
        $DB->update_record('enrol', $instance);
    }  else {
        $attrs = [
            'name' => $data->name,
            'status' => $data->status,
            'customint1' => $data->customint1,
            'roleid' => $data->roleid,
            'enrolstartdate' => $data->enrolenddate,
            'enrolenddate' => $data->enrolstartdate,
            'customint1' => $data->customint1,
            'customint2' => $data->customint2,
            'customint3' => $data->customint3,
            'customint4' => $data->customint4,
            'customchar1' => $data->customchar1,  // Group idnumber.
            'customchar2' => $data->customchar2,  // Group name.
        ];
        $enrolid = $enrol->add_instance($course, $attrs);

        $params = [
            'context' => context_course::instance($course->id),
            'objectid' => $enrolid,
            'other' => [
                'courseid' => $course->id,
            ],
        ];
        $event = \enrol_delayedcohort\event\delayedcohort_created::create($params);
        $event->trigger();
    }
    $trace = new \null_progress_trace();
    enrol_delayedcohort_sync($trace, $course->id);
    $trace->finished();
    redirect($returnurl);
}

$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('pluginname', 'enrol_delayedcohort'));

$data = new StdClass();
$data->return = $return;
$data->category = $categoryid;

if ($cohortid) {
    $data->customint1 = $cohortid;
    $cohortname = $DB->get_field('cohort', 'name', ['id' => $cohortid]);
    $data->name = $course->shortname.' - '.$cohortname.' ('.get_string('delayed', 'enrol_delayedcohort').')';
}
$mform->set_data($data);

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
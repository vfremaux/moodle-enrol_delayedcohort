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

defined('MOODLE_INTERNAL') || die();

/**
 * Adds new instance of enrol_cohort to specified course.
 *
 * @package   enrol_delayedcohort
 * @category  enrol
 * @author    Valery Fremaux <valery.fremaux@gmail.com>
 * @copyright 2015 Valery Fremaux {@link http://www.mylearningfactory.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

echo $OUTPUT->header();

echo $renderer->tabs();

echo $OUTPUT->heading(get_string('plannedcourses', 'enrol_delayedcohort'));

$plannedcourses = array();
$enrols = enrol_delayedcohort_get_planned_enrols($plannedcourses);

if (empty($enrols)) {
    echo $OUTPUT->notification(get_string('noprogrammablecohorts', 'enrol_delayedcohort'));
} else {

    $coursestr = get_string('course');
    $cohortstr = get_string('cohort', 'cohort');
    $rolestr = get_string('role');
    $datestr = get_string('triggerdate', 'enrol_delayedcohort');

    $table = new html_table();
    $table->head = array($coursestr, $cohortstr,  $rolestr, $datestr, '');
    $table->size = array('20%', '20%', '20%', '20%', '20%');
    $table->width = '100%';

    foreach ($enrols as $e) {
        $row = array();
        $course = $DB->get_record('course', array('id' => $e->courseid), 'id,shortname,fullname');
        $row[] = "$e->shortname - $e->fullname";
        $row[] = $e->chname;
        $role = $DB->get_record('role', array('id' => $e->roleid));
        $row[] = role_get_name($role);
        $row[] = userdate($e->enrolstartdate);

        $params = ['courseid' => $e->courseid, 'id' => $e->id, 'sesskey' => sesskey(), 'return' => 'planner'];
        $editurl = new moodle_url('/enrol/delayedcohort/edit.php', $params);
        $cmd = '<a href="'.$editurl.'">'.$OUTPUT->pix_icon('t/edit', get_string('update'), 'core').'</a>';

        $params = ['what' => 'delete', 'id' => $e->id, 'sesskey' => sesskey()];
        $deleteurl = new moodle_url('/enrol/delayedcohort/planner.php', $params);
        $cmd .= ' <a href="'.$deleteurl.'" style="float:right">'.$OUTPUT->pix_icon('t/delete', get_string('delete')).'</a></div>';
        $row[] = $cmd;
        
        $table->data[] = $row;
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
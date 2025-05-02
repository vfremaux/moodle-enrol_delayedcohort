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

echo $OUTPUT->heading(get_string('unplannedcourses', 'enrol_delayedcohort'));

$plannedcourses = array();
$enrols = enrol_delayedcohort_get_planned_enrols($plannedcourses, true);
if (empty($plannedcourses)) {
    $sql = '1 = 1';
    $params = [];
} else {
    list($sql, $params) = $DB->get_in_or_equal($plannedcourses, SQL_PARAMS_QM, 'param', false);
}

$coursestr = get_string('course');

$courses = $DB->get_records_select('course', "id $sql", $params, 'shortname', 'id,shortname,idnumber,fullname,visible');

if (empty($courses)) {
    echo $OUTPUT->notification(get_string('nocourses', 'enrol_delayedcohort'));
} else {
    $table = new html_table();
    $table->head = array($coursestr, '', '');
    $table->size = array('30%', '30%', '30%');
    $table->width = '100%';

    foreach ($courses as $c) {
        if ($c->id == SITEID) {
            continue;
        }
        $coursecontext = context_course::instance($c->id);
        if (!has_capability('enrol/delayedcohort:config', $coursecontext)) {
            continue;
        }
        $row = array();
        if ($c->visible) {
            $row[] = "$c->shortname - $c->fullname";
        } else {
            $row[] = '<span class="shadow">'.$c->shortname.' - '.$c->fullname.'</span>';
        }
        $instancesurl = new moodle_url('/enrol/delayedcohort/edit.php', array('courseid' => $c->id));
        $cmd = '<a href="'.$instancesurl.'">'.get_string('addenrol', 'enrol_delayedcohort').'</a>';
        $row[] = $cmd;
        $instancesurl = new moodle_url('/enrol/instances.php', array('id' => $c->id));
        $cmd = '<a href="'.$instancesurl.'">'.get_string('manageenrols', 'enrol_delayedcohort').'</a>';
        $row[] = $cmd;
        $table->data[] = $row;
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
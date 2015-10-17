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
 * @package enrol_delayedcohort
 * @copyright 2010 Petr Skoda {@link http://skodak.org}
 * @copyright 2015 Valery Fremaux {@link http://www.mylearningfactory.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('You canot use this script this way');
}

echo $OUTPUT->header();

echo $renderer->tabs();

echo $OUTPUT->heading(get_string('unplannedcourses', 'enrol_delayedcohort'));

$plannedcourses = array();
$enrols = enrol_delayedcohort_get_planned_enrols($plannedcourses, true);
if (empty($plannedcourses)) {
    $sql = '1 = 1';
    $params = array();
} else {
    list($sql, $params) = $DB->get_in_or_equal($plannedcourses, SQL_PARAMS_QM, 'param', false);
}

$coursestr = get_string('course');

if (!$courses = $DB->get_records_select('course', $sql, $params, 'id,shortname,idnumber,fullname,visible')) {
    echo $OUTPUT->notification(get_string('nocourses', 'enrol_delayedcohort'));
} else {
    $table = new html_table();
    $table->head = array($coursestr, '');
    $table->size = array('20%', '20%', '20%', '20%', '20%');
    $table->width = '100%';

    foreach ($courses as $c) {
        $row = array();
        $row[] = "$c->shortname - $c->fullname";
        $instancesurl = new moodle_url('/enrol/instances.php', array('courseid' => $c->id));
        $cmd = '<a href="'.$instancesurl.'">'.get_string('addenrol', 'enrol_delayedcohort').'</a>';
        $row = $cmd;
    }
    
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
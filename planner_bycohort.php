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
require_once($CFG->dirroot.'/lib/coursecatlib.php');

if (!defined('MOODLE_INTERNAL')) {
    die('You canot use this script this way');
}

$pagesize = 5;
$cpage = optional_param('cpage', 0, PARAM_INT);
$allcohorts = $DB->count_records('cohort');

echo $OUTPUT->header();

echo $renderer->tabs();

echo $OUTPUT->heading(get_string('cohortsplanner', 'enrol_delayedcohort'));

if (!$cohorts = $DB->get_records('cohort', array(), 'name', '*', $cpage * $pagesize, $pagesize)) {
    echo $OUTPUT->notification('nocohorts', 'enrol_delayedcohort');
}

$category = optional_param('category', 0, PARAM_INT);
$categoryclause = '';
$params = array();
if ($category) {
    $categoryclause = ' AND category = ?';
    $params[] = $category;
}

echo $OUTPUT->box_start('', 'delayedcohort-selector');
echo $renderer->category_selector($url);
echo $OUTPUT->box_end();

if (!$courses = $DB->get_records_select('course', ' ID != '.SITEID.' '.$categoryclause, $params, 'idnumber')) {
    echo $OUTPUT->box_start('delayedcohort-empty');
    echo $OUTPUT->notification('nocourses', 'enrol_delayedcohort');
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
    die;
}

echo $OUTPUT->paging_bar($allcohorts, $cpage, $pagesize, $url, $pagevar = 'cpage');

echo '<div class="outer bycohort">';
echo '<div class="inner bycohort">';
echo '<table class="bycohort">';
echo '<tr>';
echo '<th>';
echo '</th>';
foreach ($courses as $c) {
    echo '<td>';
    $courseurl = new moodle_url('/course/view.php', array('id' => $c->id));
    echo '<a href="'.$courseurl.'">'.format_string($c->shortname).'</a>';
    echo '</td>';
}
echo '</tr>';

// Get all roles for the grid.
$roles = $DB->get_records('role');

foreach($cohorts as $ch) {
    echo '<tr>';
    echo '<th>';
    echo format_string($ch->name);
    echo '<br/>';
    echo '<span class="delayedcohort-smalltext">('.$DB->count_records('cohort_members', array('cohortid' => $ch->id)).' '.get_string('users').')</span>';
    echo '</th>';
    foreach ($courses as $c) {
        $enrols = $DB->get_records('enrol', array('enrol' => 'delayedcohort', 'courseid' => $c->id, 'customint1' => $ch->id));
        echo '<td class="header">';
        if (!empty($enrols)) {
            foreach($enrols as $e) {
                $class = ($e->customint3 <= time()) ? 'delayedcohort-passed' : 'delayedcohort-future';
                $class = ($e->customint4 < time() && !empty($e->customchar1)) ? 'delayedcohort-over' : $class;
                $instanceurl = new moodle_url('/enrol/delayedcohort/edit.php', array('courseid' => $e->courseid, 'id' => $e->id, 'cohortid' => $ch->id, 'return' => 'planner_bycohort', 'sesskey' => sesskey(), 'category' => $category));
                echo '<div class="'.$class.'"><a href="'.$instanceurl.'">';
                echo (userdate($e->customint3, '%a %d/%m/%Y %H:%M'));
                $rolename = role_get_name($roles[$e->roleid]);
                echo '</a> ('.$rolename.')';
                if ($e->customint4) {
                    if ($e->customchar1) {
                        echo '<div style="float:left"><img title="'.get_string('endsat', 'enrol_delayedcohort', userdate($e->customint4)).'" src="'.$OUTPUT->pix_url('hasending', 'enrol_delayedcohort').'" /></div>';
                    } else {
                        echo '<div style="float:left"><img title="'.get_string('endsatcontinue', 'enrol_delayedcohort', userdate($e->customint4)).'" src="'.$OUTPUT->pix_url('hasendingcontinue', 'enrol_delayedcohort').'" /></div>';
                    }
                }
                $deleteurl = new moodle_url('/enrol/delayedcohort/planner.php', array('what' => 'delete', 'id' => $e->id, 'sesskey' => sesskey(), 'view' => 'bycohort', 'category' => $category));
                echo '<a href="'.$deleteurl.'" style="float:right" alt="'.get_string('delete').'"><img src="'.$OUTPUT->pix_url('t/delete').'"></a></div>';
            }
        } else {
            $addurl = '';
            $addurl = new moodle_url('/enrol/delayedcohort/edit.php', array('courseid' => $c->id, 'cohortid' => $ch->id, 'return' => 'planner_bycohort', 'sesskey' => sesskey(), 'category' => $category));
            echo '<a href="'.$addurl.'"><img class="enrol-slot" src="'.$OUTPUT->pix_url('unplanned', 'enrol_delayedcohort').'"></a>';
        }
        echo '</td>';
    }
    echo '</tr>';
}

echo '</table>';
echo '</div>';
echo '</div>';

echo $OUTPUT->paging_bar($allcohorts, $cpage, $pagesize, $url, $pagevar = 'cpage');

echo $OUTPUT->footer();
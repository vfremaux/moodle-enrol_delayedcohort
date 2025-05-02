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
 * @author    Valery Fremaux <valery.fremaux@gmail.com>
 * @copyright 2015 Valery Fremaux {@link http://www.mylearningfactory.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$pagesize = 5;
$cpage = optional_param('cpage', 0, PARAM_INT);
$allcohorts = $DB->count_records('cohort');

echo $OUTPUT->header();

echo $renderer->tabs();

echo $OUTPUT->heading(get_string('cohortsplanner', 'enrol_delayedcohort'));

$category = optional_param('category', 0, PARAM_INT); // course Category filter
$cohortfilter = optional_param('cohortfilter', '', PARAM_TEXT); // Cohort filter

$categoryclause = '';
$params = [];

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

if (!$cohorts = $DB->get_records('cohort', [], 'name', '*', $cpage * $pagesize, $pagesize)) {
    echo $OUTPUT->notification('nocohorts', 'enrol_delayedcohort');
}

$url->add_params(['cohortfilter' => $cohortfilter, 'category' => $category]);
echo $OUTPUT->paging_bar($allcohorts, $cpage, $pagesize, $url, $pagevar = 'cpage');

echo $renderer->by_cohorts($courses, $cohorts);

echo $OUTPUT->paging_bar($allcohorts, $cpage, $pagesize, $url, $pagevar = 'cpage');

echo $OUTPUT->footer();
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

class enrol_delayedcohort_renderer extends plugin_renderer_base {

    function tabs() {
        global $OUTPUT;

        $currenttab = optional_param('view', 'bycourse', PARAM_TEXT);

        $row[] = new tabobject('bycourse',
                               new moodle_url('/enrol/delayedcohort/planner.php', array('view' => 'bycourse')),
                               get_string('bycourse', 'enrol_delayedcohort'));

        $row[] = new tabobject('bycohort',
                               new moodle_url('/enrol/delayedcohort/planner.php', array('view' => 'bycohort')),
                               get_string('bycohort', 'enrol_delayedcohort'));

        $row[] = new tabobject('unassigned',
                               new moodle_url('/enrol/delayedcohort/planner.php', array('view' => 'unassigned')),
                               get_string('unassigned', 'enrol_delayedcohort'));

        $str = '<div class="enrol-delayedcohort-tabs">';
        $str .= $OUTPUT->tabtree($row, $currenttab);
        $str .= '</div>';

        return $str;
    }

    function category_selector($url) {
        global $OUTPUT;

        $str = '';
        $choice = optional_param('category', 0, PARAM_INT);
        $categories = coursecat::make_categories_list();
        $str .= $OUTPUT->single_select($url, 'category', $categories, $choice, array('' => get_string('all', 'enrol_delayedcohort')));

        return $str;
    }
}
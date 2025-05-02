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

namespace enrol_delayedcohort\output;

defined('MOODLE_INTERNAL') || die();

use tabobject;
use moodle_url;

/**
 * Adds new instance of enrol_cohort to specified course.
 *
 * @package   enrol_delayedcohort
 * @author    Valery Fremaux <valery.fremaux@gmail.com>
 * @copyright 2015 Valery Fremaux {@link http://www.mylearningfactory.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class renderer extends \plugin_renderer_base {

    function tabs() {
        global $OUTPUT;

        $currenttab = optional_param('view', 'bycourse', PARAM_TEXT);

        $row[] = new tabobject('bycourse',
                               new moodle_url('/enrol/delayedcohort/planner.php', ['view' => 'bycourse']),
                               get_string('bycourse', 'enrol_delayedcohort'));

        $row[] = new tabobject('bycohort',
                               new moodle_url('/enrol/delayedcohort/planner.php', ['view' => 'bycohort']),
                               get_string('bycohort', 'enrol_delayedcohort'));

        $row[] = new tabobject('unassigned',
                               new moodle_url('/enrol/delayedcohort/planner.php', ['view' => 'unassigned']),
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
        $categories = \core_course_category::make_categories_list();
        $str .= $OUTPUT->single_select($url, 'category', $categories, $choice, ['' => get_string('all', 'enrol_delayedcohort')]);

        return $str;
    }

    public function by_cohorts_table() {
        global $OUTPUT;

        $template = new StdClass;
        foreach ($courses as $c) {
            $coursetpl = new StdClass;
            $coursetpl->courseurl = new moodle_url('/course/view.php', ['id' => $c->id]);
            $coursetpl->shortname = format_string($c->shortname);
            $coursetpl->fullname = format_string($c->fullname);
            $template->courses[] = $coursetpl;
        }

        // Get all roles for the grid.
        $roles = $DB->get_records('role');

        foreach($cohorts as $ch) {
            $cohorttpl = new stdClass;
            $cohorttpl->name = format_string($ch->name);
            $cohorttpl->count = $DB->count_records('cohort_members', ['cohortid' => $ch->id]);

            foreach ($courses as $c) {
                $coursetpl = new StdClass;
                $enrols = $DB->get_records('enrol', ['enrol' => 'delayedcohort', 'courseid' => $c->id, 'customint1' => $ch->id]);
                if (!empty($enrols)) {
                    foreach($enrols as $e) {
                        $enroltpl = new StdClass;
                        $class = ($e->enrolstartdate <= time()) ? 'delayedcohort-passed' : 'delayedcohort-future';
                        $enroltpl->class = ($e->enrolenddate < time() && !empty($e->customchar1)) ? 'delayedcohort-over' : $class;
                        $params = [
                            'courseid' => $e->courseid,
                            'id' => $e->id,
                            'cohortid' => $ch->id,
                            'return' => 'planner_bycohort',
                            'sesskey' => sesskey(),
                            'category' => $category
                        ];
                        $enroltpl->instanceurl = new moodle_url('/enrol/delayedcohort/edit.php', $params);
                        $enroltpl->startdate = (userdate($e->enrolstartdate, '%a %d/%m/%Y %H:%M'));
                        $enroltpl->rolename = role_get_name($roles[$e->roleid]);
                        if ($e->enrolenddate) {
                            $dateend = userdate($e->enrolenddate);
                            $enroltpl->linkurl = get_string('endsat', 'enrol_delayedcohort', $dateend);
                            $enroltpl->image = $OUTPUT->image_url('hasending', 'enrol_delayedcohort');
                        } else {
                            if ($e->customint4) { // legal end, but no formal ending.
                                $dateend = userdate($e->customint4);
                                $enroltpl->linkurl = get_string('endsatcontinue', 'enrol_delayedcohort', $dateend);
                                $enroltpl->image = $OUTPUT->image_url('hasendingcontinue', 'enrol_delayedcohort');
                            } else { // Nothing ends.
                                $enroltpl->linkurl = get_string('noend', 'enrol_delayedcohort', $dateend);
                                $enroltpl->image = $OUTPUT->image_url('noend', 'enrol_delayedcohort');
                            }
                        }
                        $params = [
                            'what' => 'delete',
                            'id' => $e->id,
                            'sesskey' => sesskey(),
                            'view' => 'bycohort',
                            'category' => $category,
                        ];
                        $enroltpl->deleteurl = new moodle_url('/enrol/delayedcohort/planner.php', $params);
                        $coursetpl->enrols[] = $enroltpl;
                    }
                } else {
                    $params = [
                        'courseid' => $c->id,
                        'cohortid' => $ch->id,
                        'return' => 'planner_bycohort',
                        'sesskey' => sesskey(),
                        'category' => $category,
                    ];
                    $coursetpl->addurl = new moodle_url('/enrol/delayedcohort/edit.php', $params);
                    $coursetpl->unplannedurl = $OUTPUT->image_url('unplanned', 'enrol_delayedcohort');
                }
            }
        }

        return $this->output->render_from_template('enrol_delayedcohort/by_cohorts', $template);
    }
}
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
 * quizaccess_usernumattempts data generator.
 *
 * @package     enrol_delayedcohort
 * @subpackage  test
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * enrol_delayedcohort data generator class.
 */
class enrol_delayedcohort_generator extends \component_generator_base {

    public function generate_courses() {
        global $DB;

        $coursedata1 = new stdClass();
        $coursedata1->idnumber = 'IDNUM1';
        $coursedata1->shortname = 'SHORT1';
        $coursedata1->fullname = 'Course 1';
        $coursedata1->visible = 1;

        $coursedata2 = new stdClass();
        $coursedata2->visible = 0;
        $coursedata2->idnumber = 'IDNUM2';
        $coursedata2->shortname = 'SHORT2';
        $coursedata2->fullname = 'Course 2';

        $coursedata3 = new stdClass();
        $coursedata3->visible = 0;
        $coursedata3->idnumber = 'IDNUM3';
        $coursedata3->shortname = 'SHORT3';
        $coursedata3->fullname = 'Course 3';

        $coursedata4 = new stdClass();
        $coursedata4->visible = 0;
        $coursedata4->idnumber = 'IDNUM4';
        $coursedata4->shortname = 'SHORT4';
        $coursedata4->fullname = 'Course 4';

        return [$coursedata1, $coursedata2, $coursedata3, $coursedata4];
    }

    public function generate_users() {
        $student1 = $this->create_user();
        $student2 = $this->create_user();
        $student3 = $this->create_user();
        return [$student1, $student2, $student3];
    }

    public function generate_cohorts() {
        $cohortdata = new StdClass;
        $cohortdata->contextid = \context_system::instance()->id;
        $cohortdata->name = 'COHORT 1';
        $cohortdata->idnumber = 'COHORT1';
        $cohortdata->visible = true;
        return [$cohortdata];
    }

    public function generate_instances($cohorts, $forws = false) {
        global $DB;

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);

        $instancedata = new StdClass;
        $instancedata->name = "Cohortdelayed 1";
        $instancedata->cohortid = $cohorts[0]->id;
        if ($forws) {
            $instancedata->roleid = 'student';
        } else {
            $instancedata->roleid = $studentrole->id;
        }
        $instancedata->grouppropmode = 0;
        $instancedata->enrolstartdate = time() + HOURSECS;
        $instancedata->enrolenddate = time() + 62 * DAYSECS;
        $instancedata->legalstartdate = time() - DAYSECS;
        $instancedata->legalenddate = time() + 60 * DAYSECS;
        $instancedata->status = ENROL_INSTANCE_ENABLED;

        return [$instancedata];
    }

}


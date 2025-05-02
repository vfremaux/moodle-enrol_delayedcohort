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
 * Self enrol external PHPunit tests
 *
 * @package   enrol_delayedcohort
 * @author    Valery Fremaux <valery.fremaux@gmail.com>
 * @copyright 2015 Valery Fremaux {@link http://www.mylearningfactory.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_delayedcohort;

defined('MOODLE_INTERNAL') || die();

use context_course;

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/enrol/delayedcohort/tests/generator/lib.php');
require_once($CFG->dirroot . '/enrol/delayedcohort/externallib.php');
require_once($CFG->dirroot . '/cohort/lib.php');

use enrol_delayedcohort_external;
use external_api;

/**
 * Guest enrolment external functions tests
 *
 * @package    enrol_delayedcohort
 * @author    Valery Fremaux <valery.fremaux@gmail.com>
 * @copyright 2015 Valery Fremaux {@link http://www.mylearningfactory.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external_test extends \externallib_advanced_testcase {

    /**
     * Test get_instance_info
     */
    public function test_get_instance_info() {
        global $DB;

        $this->resetAfterTest(true);
        $dgen = self::getDataGenerator();
        $gen = $this->getDataGenerator()->get_plugin_generator('enrol_delayedcohort');

        // Check if guest enrolment plugin is enabled.
        $syncplugin = enrol_get_plugin('delayedcohort');
        $this->assertNotEmpty($syncplugin);

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);

        list($coursedata1, $unused) = $gen->generate_courses();
        $course = $dgen->create_course($coursedata1);

        $student = $dgen->create_user();

        list($cohort) = $gen->generate_cohorts();
        $cohort->id = cohort_add_cohort($cohort);

        $this->setAdminUser();

        // Add enrolment methods for course.
        list($instance) = $gen->generate_instances([$cohort], false);
        $instanceid = $syncplugin->add_instance($course, (array) $instance);

        $result = enrol_delayedcohort_external::get_instance_info($instanceid);
        $result = external_api::clean_returnvalue(enrol_delayedcohort_external::get_instance_info_returns(), $result);

        $this->assertEquals($instanceid, $result['instanceinfo']['id']);
        $this->assertEquals($course->id, $result['instanceinfo']['courseid']);
        $this->assertEquals('delayedcohort', $result['instanceinfo']['type']);
        $this->assertEquals('Cohortdelayed 1', $result['instanceinfo']['name']);
        $this->assertTrue($result['instanceinfo']['status']); // Positive logic out of get_instance_info.

        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $instanceid]);

        $result = enrol_delayedcohort_external::get_instance_info($instanceid);
        $result = external_api::clean_returnvalue(enrol_delayedcohort_external::get_instance_info_returns(), $result);
        $this->assertEquals($instanceid, $result['instanceinfo']['id']);
        $this->assertEquals($course->id, $result['instanceinfo']['courseid']);
        $this->assertEquals('delayedcohort', $result['instanceinfo']['type']);
        $this->assertEquals('Cohortdelayed 1', $result['instanceinfo']['name']);
        $this->assertFalse($result['instanceinfo']['status']); // Positive logic out of get_instance_info.

        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['id' => $instanceid]);

        // Try to retrieve information using a normal user for a hidden course.
        $user = $dgen->create_user();
        $this->setUser($user);
        try {
            enrol_delayedcohort_external::get_instance_info($instanceid);
        } catch (moodle_exception $e) {
            $this->assertEquals('coursehidden', $e->errorcode);
        }

        // Student user.
        $DB->set_field('course', 'visible', 1, ['id' => $course->id]);
        $this->setUser($student);
        $result = enrol_delayedcohort_external::get_instance_info($instanceid);
        $result = external_api::clean_returnvalue(enrol_delayedcohort_external::get_instance_info_returns(), $result);

        $this->assertEquals($instanceid, $result['instanceinfo']['id']);
        $this->assertEquals($course->id, $result['instanceinfo']['courseid']);
        $this->assertEquals('delayedcohort', $result['instanceinfo']['type']);
        $this->assertEquals('Cohortdelayed 1', $result['instanceinfo']['name']);
        $this->assertTrue($result['instanceinfo']['status']);

        $instance = $DB->get_record('enrol', ['id' => $instanceid]);
        $syncplugin->delete_instance($instance);
    }

    /**
     * Test get_instances
     */
    public function test_get_instances() {
        global $DB;

        $this->resetAfterTest(true);
        $dgen = self::getDataGenerator();
        $gen = $this->getDataGenerator()->get_plugin_generator('enrol_delayedcohort');

        // Check if guest enrolment plugin is enabled.
        $syncplugin = enrol_get_plugin('delayedcohort');
        $this->assertNotEmpty($syncplugin);

        list($coursedata1, $unused) = $gen->generate_courses();
        $course1 = $dgen->create_course($coursedata1);

        list($cohort) = $gen->generate_cohorts();
        $cohort->id = cohort_add_cohort($cohort);

        list($instance) = $gen->generate_instances([$cohort], false);

        // Add enrolment methods for course.
        $instanceid = $syncplugin->add_instance($course1, (array) $instance);

        $this->setAdminUser();

        // Check instances by courseid
        $result = enrol_delayedcohort_external::get_instances('id', $course1->id);
        $result = external_api::clean_returnvalue(enrol_delayedcohort_external::get_instances_returns(), $result);

        $this->assertEquals($instanceid, $result[0]['id']);
        $this->assertEquals($course1->id, $result[0]['courseid']);
        $this->assertEquals('delayedcohort', $result[0]['type']);
        $this->assertEquals('Cohortdelayed 1', $result[0]['name']);
        $this->assertTrue($result[0]['status']); // positive logic is back

        // Check instances by shortname
        $result = enrol_delayedcohort_external::get_instances('shortname', 'SHORT1');
        $result = external_api::clean_returnvalue(enrol_delayedcohort_external::get_instances_returns(), $result);

        $this->assertEquals($instanceid, $result[0]['id']);
        $this->assertEquals($course1->id, $result[0]['courseid']);
        $this->assertEquals('delayedcohort', $result[0]['type']);
        $this->assertEquals('Cohortdelayed 1', $result[0]['name']);
        $this->assertTrue($result[0]['status']); // positive logic is back.

        // Check instances by idnumber
        $result = enrol_delayedcohort_external::get_instances('idnumber', 'IDNUM1');
        $result = external_api::clean_returnvalue(enrol_delayedcohort_external::get_instances_returns(), $result);

        $this->assertEquals($instanceid, $result[0]['id']);
        $this->assertEquals($course1->id, $result[0]['courseid']);
        $this->assertEquals('delayedcohort', $result[0]['type']);
        $this->assertEquals('Cohortdelayed 1', $result[0]['name']);
        $this->assertTrue($result[0]['status']); // positive logic is back.

        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $instanceid]);

        // Test with status changed.
        $result = enrol_delayedcohort_external::get_instance_info($instanceid);
        $result = external_api::clean_returnvalue(enrol_delayedcohort_external::get_instance_info_returns(), $result);

        $this->assertEquals($instanceid, $result['instanceinfo']['id']);
        $this->assertEquals($course1->id, $result['instanceinfo']['courseid']);
        $this->assertEquals('delayedcohort', $result['instanceinfo']['type']);
        $this->assertEquals('Cohortdelayed 1', $result['instanceinfo']['name']);
        $this->assertFalse($result['instanceinfo']['status']); // Instance info status gives back to positive logic.

        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['id' => $instanceid]);

        $instance = $DB->get_record('enrol', ['id' => $instanceid]);
        $syncplugin->delete_instance($instance);
    }

    /**
     * Test get_enrollable_users
     */
    public function test_add_method_to_course() {
        global $DB;
        global $CFG;

        // Ensure plugin is enabled.
        set_config('enrol_plugins_enabled', $CFG->enrol_plugins_enabled.',delayedcohort');

        $this->resetAfterTest(true);
        $dgen = self::getDataGenerator();
        $gen = $this->getDataGenerator()->get_plugin_generator('enrol_delayedcohort');

        // Check if guest enrolment plugin is enabled.
        $syncplugin = enrol_get_plugin('delayedcohort');
        $this->assertNotEmpty($syncplugin);

        list($coursedata1, $unused) = $gen->generate_courses();
        $course1 = $dgen->create_course($coursedata1);
        $student1 = $dgen->create_user();
        $student2 = $dgen->create_user();
        $student3 = $dgen->create_user();

        list($cohort) = $gen->generate_cohorts();
        $cohort->id = cohort_add_cohort($cohort);

        cohort_add_member($cohort->id, $student1->id);
        cohort_add_member($cohort->id, $student2->id);
        cohort_add_member($cohort->id, $student3->id);

        $this->setAdminUser();

        // Put an instance in the future. Users should NOT be enrolled.
        list($instance) = $gen->generate_instances([$cohort], true);
        // Check instance returned after adding method to course.
        $result = enrol_delayedcohort_external::add_method_to_course('id', 'id', 'shortname', $course1->id, (array) $instance);
        $result = external_api::clean_returnvalue(enrol_delayedcohort_external::add_method_to_course_returns(), $result);

        $this->assertNotNull($result['instanceinfo']['id']);
        $this->assertEquals($course1->id, $result['instanceinfo']['courseid']);
        $this->assertEquals('delayedcohort', $result['instanceinfo']['type']);
        $this->assertEquals('Cohortdelayed 1', $result['instanceinfo']['name']);
        $this->assertTrue($result['instanceinfo']['status']);

        // countercheck by get_instances.
        $countercheck = enrol_delayedcohort_external::get_instances('id', $course1->id, $instance);
        $countercheck = external_api::clean_returnvalue(enrol_delayedcohort_external::get_instances_returns(), $countercheck);
        $this->assertEquals($countercheck[0]['id'], $result['instanceinfo']['id']);
        $this->assertTrue($result['instanceinfo']['status']);

        // Try to update an instance with passed stat date. Users should be enrolled.

        $newstart = time() + 2 * HOURSECS;
        $instance->enrolstartdate = $newstart;
        $result = enrol_delayedcohort_external::add_method_to_course('id', 'id', 'shortname', $course1->id, (array) $instance);
        $result = external_api::clean_returnvalue(enrol_delayedcohort_external::add_method_to_course_returns(), $result);

        $this->assertNotNull($result['instanceinfo']['id']);
        $this->assertEquals($course1->id, $result['instanceinfo']['courseid']);
        $this->assertEquals($newstart, $result['instanceinfo']['enrolstartdate']);

        // delete instance using external services.

        $result = enrol_delayedcohort_external::delete_method_instance('id', 'id', $course1->id, $cohort->id);

        $this->assertEquals(0, $DB->count_records('enrol', ['courseid' => $course1->id, 'enrol' => 'delayedcohort']));

    }

    /**
     * Test get_enrollable_users
     */
    public function test_get_enrollable_users() {
        global $DB;
        global $CFG;

        $this->resetAfterTest(true);

        // Ensure plugin is enabled.
        set_config('enrol_plugins_enabled', $CFG->enrol_plugins_enabled.',delayedcohort');

        $dgen = self::getDataGenerator();
        $gen = $this->getDataGenerator()->get_plugin_generator('enrol_delayedcohort');

        // Check if guest enrolment plugin is enabled.
        $syncplugin = enrol_get_plugin('delayedcohort');
        $this->assertNotEmpty($syncplugin);

        list($coursedata1, $coursedata2, $coursedata3, $coursedata4) = $gen->generate_courses();
        $course1 = $dgen->create_course($coursedata1);
        $course2 = $dgen->create_course($coursedata2);
        $course3 = $dgen->create_course($coursedata3);
        $course4 = $dgen->create_course($coursedata4);
        $context1 = context_course::instance($course1->id);
        $context2 = context_course::instance($course2->id);
        $context3 = context_course::instance($course3->id);
        $context4 = context_course::instance($course4->id);

        list($cohort) = $gen->generate_cohorts();
        $cohort->id = cohort_add_cohort($cohort);

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);

        // Add enrolment methods
        $futureinstanceid = $syncplugin->add_instance($course1, [
            'status' => ENROL_INSTANCE_ENABLED,
            'name' => 'Test instance future',
            'roleid' => $studentrole->id, // Role id
            'enrolstartdate' => time() + HOURSECS, // Start time
            'enrolenddate' => time() + HOURSECS * 24, // End time
            'customint1' => $cohort->id, // Cohort id
            'customint2' => 0, // Group mode
            'customint3' => time() + HOURSECS, // legalstarttime
            'customint4' => time() + HOURSECS * 24, // legalendtime
            'customchar1' => '', // Group idnumber
            'customchar2' => '', // Group name
            'status' => 0,
        ]);
        $futureinstance = $DB->get_record('enrol', ['id' => $futureinstanceid]);
        $this->assertNotEmpty($futureinstance->id, 'Could not create future instance properly');

        $activeinstanceid = $syncplugin->add_instance($course2, [
            'status' => ENROL_INSTANCE_ENABLED,
            'name' => 'Test instance active',
            'roleid' => $studentrole->id, // Role id
            'enrolstartdate' => time() - HOURSECS, // starttime
            'enrolenddate' => time() + HOURSECS * 24, // endttime
            'customint1' => $cohort->id, // cohortid
            'customint2' => 0, // Group mode
            'customint3' => time() - HOURSECS, // // legalstarttime
            'customint4' => time() + HOURSECS * 24, // legalendtime
            'customchar1' => '', // Group idnumber
            'customchar2' => '', // Group name
            'status' => 0,
        ]);
        $activeinstance = $DB->get_record('enrol', ['id' => $activeinstanceid]);
        $this->assertNotEmpty($activeinstance->id, 'Could not create active instance properly');

        // Passed (over) instance
        $passedinstanceid = $syncplugin->add_instance($course3, [
            'status' => ENROL_INSTANCE_ENABLED,
            'name' => 'Test instance passed',
            'roleid' => $studentrole->id, // Role id
            'enrolstartdate' => time() - (HOURSECS * 24), // starttime
            'enrolenddate' => time() - HOURSECS, // endtime
            'customint1' => $cohort->id, // Cohort id
            'customint2' => 0, // Group mode
            'customint3' => time() - (HOURSECS * 24), // legalstarttime
            'customint4' => time() - HOURSECS, // legalendtime
            'customchar1' => '', // Group idnumber
            'customchar2' => '', // Group name
            'status' => 0,
        ]);
        $passedinstance = $DB->get_record('enrol', ['id' => $passedinstanceid]);
        $this->assertNotEmpty($passedinstance->id, 'Could not create passed instance properly');

        // Passed (over) instance without enddate
        $passingoverinstanceid = $syncplugin->add_instance($course4, [
            'status' => ENROL_INSTANCE_ENABLED,
            'name' => 'Test instance passed no end date',
            'enrolstartdate' => time() - (HOURSECS * 24), // starttime
            'enrolenddate' => 0, // endtime
            'roleid' => $studentrole->id, // Role id
            'customint1' => $cohort->id, // Cohort id
            'customint2' => 0, // Group mode
            'customint3' => time() - (HOURSECS * 24), // legalstarttime
            'customint4' => time() - HOURSECS, // legalendtime
            'customchar1' => '', // Group idnumber
            'customchar2' => '', // Group name
            'status' => 0,
        ]);
        $passingoverinstance = $DB->get_record('enrol', ['id' => $passingoverinstanceid]);
        $this->assertNotEmpty($passingoverinstance->id, 'Could not create passingover instance properly');

        $this->setAdminUser();

        $student1 = $dgen->create_user();
        $student2 = $dgen->create_user();
        $student3 = $dgen->create_user();
        cohort_add_member($cohort->id, $student1->id);
        cohort_add_member($cohort->id, $student2->id);
        cohort_add_member($cohort->id, $student3->id);

        ob_start(); // Avoid risky signal.
        // Run cron task to perform enrollment syncs.
        $task = new \enrol_delayedcohort\task\enrolsync_task;
        $task->execute();
        ob_end_clean();

        // course 1 has future cohort. Users should NOT be seen enrolled.
        $this->assertFalse(is_enrolled($context1, $student1));
        $this->assertFalse(is_enrolled($context1, $student2));
        $this->assertFalse(is_enrolled($context1, $student3));

        // course 2 has running cohort. Users should be seen enrolled.
        $this->assertTrue(is_enrolled($context2, $student1));
        $this->assertTrue(is_enrolled($context2, $student2));
        $this->assertTrue(is_enrolled($context2, $student3));

        // course 3 has cohort with enddate. Users should NOT be seen enrolled any more.
        $this->assertFalse(is_enrolled($context3, $student1));
        $this->assertFalse(is_enrolled($context3, $student2));
        $this->assertFalse(is_enrolled($context3, $student3));

        // course 4 has cohort without enddate. Users should be seen enrolled.
        $this->assertTrue(is_enrolled($context4, $student1));
        $this->assertTrue(is_enrolled($context4, $student2));
        $this->assertTrue(is_enrolled($context4, $student3));

        // Check get_enrollable_users in course 1 (future).
        $result = enrol_delayedcohort_external::get_enrollable_users('shortname', 'SHORT1', $futureinstance->id);
        $result = external_api::clean_returnvalue(enrol_delayedcohort_external::get_enrollable_users_returns(), $result);

        // Users should be present, but not seen enrolled in any course.
        $this->assertEquals($student1->id, $result[0]['id']);
        $this->assertEmpty($result[0]['enrolledcourses'], 'There should not be enrolled courses for student1');
        $this->assertEquals($student2->id, $result[1]['id']);
        $this->assertEmpty($result[1]['enrolledcourses'], 'There should not be enrolled courses for student1');

        // Check get_enrollable_users in course 2 (active).
        $result = enrol_delayedcohort_external::get_enrollable_users('shortname', 'SHORT2', $activeinstance->id);
        $result = external_api::clean_returnvalue(enrol_delayedcohort_external::get_enrollable_users_returns(), $result);

        // Users should be present, but seen enrolled in course 2.
        $this->assertEquals($student1->id, $result[0]['id']);
        $this->assertEquals($course2->id, $result[0]['enrolledcourses'][0]['id']);
        $this->assertEquals($student2->id, $result[1]['id']);
        $this->assertEquals($course2->id, $result[1]['enrolledcourses'][0]['id']);

        // Check get_enrollable_users in course 3 (passed with end date).
        $result = enrol_delayedcohort_external::get_enrollable_users('shortname', 'SHORT3', $passedinstance->id);
        $result = external_api::clean_returnvalue(enrol_delayedcohort_external::get_enrollable_users_returns(), $result);

        $this->assertEquals($student1->id, $result[0]['id']);
        $this->assertEmpty($result[0]['enrolledcourses'], 'There should not be enrolled courses for student1');
        $this->assertEquals($student2->id, $result[1]['id']);
        $this->assertEmpty($result[1]['enrolledcourses'], 'There should not be enrolled courses for student2');

        // Check get_enrollable_users in course 4.
        $result = enrol_delayedcohort_external::get_enrollable_users('shortname', 'SHORT4', $passingoverinstance->id);
        $result = external_api::clean_returnvalue(enrol_delayedcohort_external::get_enrollable_users_returns(), $result);

        $this->assertEquals($student1->id, $result[0]['id']);
        $this->assertEquals($course4->id, $result[0]['enrolledcourses'][0]['id']);
        $this->assertEquals($student2->id, $result[1]['id']);
        $this->assertEquals($course4->id, $result[1]['enrolledcourses'][0]['id']);
    }
}

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
 * Cohort enrolment sync functional test.
 *
 * @package    enrol_delayedcohort
 * @category   phpunit
 * @copyright  2025 Valery Fremaux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_delayedcohort;

defined('MOODLE_INTERNAL') || die();

use context_course;
use context_coursecat;

global $CFG;
require_once($CFG->dirroot.'/enrol/delayedcohort/locallib.php');
require_once($CFG->dirroot.'/enrol/delayedcohort/classes/plugin.php');
require_once($CFG->dirroot.'/cohort/lib.php');
require_once($CFG->dirroot.'/group/lib.php');

class setup_test extends \advanced_testcase {

    protected function enable_plugin() {
        $enabled = enrol_get_plugins(true);
        $enabled['delayedcohort'] = true;
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));
    }

    protected function disable_plugin() {
        $enabled = enrol_get_plugins(true);
        unset($enabled['delayedcohort']);
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));
    }

    public function test_add_instance() {
        global $DB;

        $this->resetAfterTest();

        // Setup a few courses and categories.

        $cohortplugin = enrol_get_plugin('delayedcohort');
        $now = time();

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->assertNotEmpty($studentrole);

        $cat = $this->getDataGenerator()->create_category();

        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);

        $user = $this->getDataGenerator()->create_user();

        $cohort1 = $this->getDataGenerator()->create_cohort(['idnumber' => 'COHORTD1', 'name' => 'Cohort Delayed 1']);
        $cohort2 = $this->getDataGenerator()->create_cohort(['idnumber' => 'COHORTD2', 'name' => 'Cohort Delayed 2']);

        $this->enable_plugin();

        $params = [
            'courseid' => $course->id,
            'customint1' => $cohort1->id,
            'customint2' => \enrol_delayedcohort_plugin::MAKE_GROUP_FROM_COHORT,
            'enrolstartdate' => $now - 6 * DAYSECS,
            'enrolenddate' => $now + 15 * DAYSECS,
            'customint3' => $now - 3 * DAYSECS,
            'customint4' => $now + 10 * DAYSECS,
            'roleid' => $studentrole->id,
        ];
        $id = $cohortplugin->add_instance($course, $params);
        $cohortinstance = $DB->get_record('enrol', ['id' => $id]);

        $this->assertEquals($cohortinstance->customint1, $cohort1->id);
        $this->assertEquals($cohortinstance->customint2, \enrol_delayedcohort_plugin::MAKE_GROUP_FROM_COHORT);
        $this->assertEquals($cohortinstance->customint3, $now - 3 * DAYSECS);
        $this->assertEquals($cohortinstance->customint4, $now + 10 * DAYSECS);

        // Check instance crrated with colomns aliases.
        $params = [
            'courseid' => $course->id,
            'cohortid' => $cohort2->id,
            'grouppropmode' => \enrol_delayedcohort_plugin::MAKE_GROUP_FROM_COHORT,
            'enrolstartdate' => $now - 6 * DAYSECS,
            'enrolenddate' => $now + 15 * DAYSECS,
            'legalstartdate' => $now - 3 * DAYSECS,
            'legalenddate' => $now + 10 * DAYSECS,
            'roleid' => $studentrole->id,
        ];
        $id = $cohortplugin->add_instance($course, $params);
        $cohortinstance = $DB->get_record('enrol', ['id' => $id]);

        $this->assertEquals($cohortinstance->customint1, $cohort2->id);
        $this->assertEquals($cohortinstance->customint2, \enrol_delayedcohort_plugin::MAKE_GROUP_FROM_COHORT);
        $this->assertEquals($cohortinstance->customint3, $now - 3 * DAYSECS);
        $this->assertEquals($cohortinstance->customint4, $now + 10 * DAYSECS);

    }

    public function test_setup_group_from_cohort() {
        global $DB;

        $this->resetAfterTest();

        // Setup a few courses and categories.

        $cohortplugin = enrol_get_plugin('delayedcohort');

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->assertNotEmpty($studentrole);

        $cat = $this->getDataGenerator()->create_category();

        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);

        $user = $this->getDataGenerator()->create_user();

        $cohort = $this->getDataGenerator()->create_cohort(['idnumber' => 'COHORTD1', 'name' => 'Cohort Delayed 1']);

        $this->enable_plugin();

        $params = [
            'courseid' => $course->id,
            'customint1' => $cohort->id,
            'customint2' => \enrol_delayedcohort_plugin::MAKE_GROUP_FROM_COHORT,
            'roleid' => $studentrole->id,
        ];
        $id = $cohortplugin->add_instance($course, $params);
        $cohortinstance = $DB->get_record('enrol', ['id' => $id]);

        // Check group was created with cohort name.

        $this->assertTrue($DB->record_exists('groups', ['idnumber' => 'AUTO-COHORTD1']));

        cohort_add_member($cohort->id, $user->id);

        // Sync.
        $trace = new \null_progress_trace();
        enrol_delayedcohort_sync($trace, $course->id);

        // Check user arrives in the group after sync.
        $group = $DB->get_record('groups', ['idnumber' => 'AUTO-COHORTD1']);
        $this->assertTrue(groups_is_member($group->id, $user->id));

        // Remove instance and check group is gone away.
        $cohortplugin->delete_instance($cohortinstance);
        $this->assertFalse($DB->record_exists('groups', ['id' => $group->id]));
        $this->assertFalse(groups_is_member($group->id, $user->id));

    }

    /**
     *
     */
    public function test_setup_group_from_form() {
        global $DB;

        $this->resetAfterTest();

        // Setup a few courses and categories.

        $cohortplugin = enrol_get_plugin('delayedcohort');

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->assertNotEmpty($studentrole);

        $cat = $this->getDataGenerator()->create_category();

        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        $context = context_course::instance($course->id);

        $user = $this->getDataGenerator()->create_user();

        $cohort = $this->getDataGenerator()->create_cohort(['idnumber' => 'COHORTD1', 'name' => 'Cohort Delayed 1']);

        $this->enable_plugin();

        $params = [
            'courseid' => $course->id,
            'customint1' => $cohort->id,
            'customint2' => \enrol_delayedcohort_plugin::MAKE_GROUP_FROM_FORM,
            'roleid' => $studentrole->id,
            'customchar1' => 'GROUPDC1',
            'customchar2' => 'Group Delayed Cohort 1',
        ];
        $id = $cohortplugin->add_instance($course, $params);
        $cohortinstance = $DB->get_record('enrol', ['id' => $id]);

        // Check group was created with cohort name.

        $this->assertTrue($DB->record_exists('groups', ['idnumber' => 'AUTO-GROUPDC1']));
        $group = $DB->get_record('groups', ['idnumber' => 'AUTO-GROUPDC1']);
        $this->assertEquals('Group Delayed Cohort 1', $group->name);

        cohort_add_member($cohort->id, $user->id);

        $trace = new \null_progress_trace();

        // Sync.
        enrol_delayedcohort_sync($trace, $course->id);

        // Check user arrives in the group after sync.
        $this->assertTrue(is_enrolled($context, $user));
        $this->assertTrue(groups_is_member($group->id, $user->id));

        // Test removing.
        cohort_remove_member($cohort->id, $user->id);
        enrol_delayedcohort_sync($trace, $course->id);
        $this->assertFalse(groups_is_member($group->id, $user->id));

        // Remove instance and check group is gone away.
        $cohortplugin->delete_instance($cohortinstance);
        $this->assertFalse($DB->record_exists('groups', ['id' => $group->id]));
        $this->assertFalse(groups_is_member($group->id, $user->id));

    }

    /**
     *
     */
    public function test_use_existing_group() {
        global $DB;

        $this->resetAfterTest();

        // Setup a few courses and categories.

        $cohortplugin = enrol_get_plugin('delayedcohort');

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->assertNotEmpty($studentrole);

        $cat = $this->getDataGenerator()->create_category();

        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        $context = context_course::instance($course->id);

        $groupdata = [
            'courseid' => $course->id,
            'name' => 'Group 1',
            'idnumber' => 'GROUP1',
        ];
        $groupid = groups_create_group((object) $groupdata);
        $group = $DB->get_record('groups', ['id' => $groupid]);

        $user = $this->getDataGenerator()->create_user();

        $cohort = $this->getDataGenerator()->create_cohort(['idnumber' => 'COHORTD1', 'name' => 'Cohort Delayed 1']);

        $this->enable_plugin();

        $params = [
            'courseid' => $course->id,
            'customint1' => $cohort->id,
            'customint2' => $groupid,
            'roleid' => $studentrole->id,
            'customchar1' => '',
            'customchar2' => '',
        ];
        $id = $cohortplugin->add_instance($course, $params);
        $cohortinstance = $DB->get_record('enrol', ['id' => $id]);

        // Check group.

        cohort_add_member($cohort->id, $user->id);

        $trace = new \null_progress_trace();

        // Sync.
        enrol_delayedcohort_sync($trace, $course->id);

        // Check user arrives in the group after sync.
        $this->assertTrue(is_enrolled($context, $user));
        $this->assertTrue(groups_is_member($group->id, $user->id));

        // Test removing.
        cohort_remove_member($cohort->id, $user->id);
        enrol_delayedcohort_sync($trace, $course->id);
        $this->assertFalse(is_enrolled($context, $user));
        $this->assertFalse(groups_is_member($group->id, $user->id));

        // Remove instance and check group is NOT gone away as existing, but user is out as unsynced.
        $cohortplugin->delete_instance($cohortinstance);
        $this->assertTrue($DB->record_exists('groups', ['id' => $group->id]));
        $this->assertFalse(groups_is_member($group->id, $user->id));
    }
}

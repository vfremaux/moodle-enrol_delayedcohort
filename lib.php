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
 * Cohort enrolment plugin.
 *
 * @package   enrol_delayedcohort
 * @category  enrol
 * @author    Valery Fremaux <valery.fremaux@gmail.com>
 * @copyright 2015 Valery Fremaux {@link http://www.mylearningfactory.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Cohort enrolment plugin implementation.
 * @author Petr Skoda
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_delayedcohort_plugin extends enrol_plugin {

    /**
     * Returns localised name of enrol instance.
     *
     * @param stdClass $instance (null is accepted too)
     * @return string
     */
    public function get_instance_name($instance) {
        global $DB;

        if (empty($instance)) {
            $enrol = $this->get_name();
            return get_string('pluginname', 'enrol_'.$enrol);

        } else if (empty($instance->name)) {
            $enrol = $this->get_name();
            $cohort = $DB->get_record('cohort', array('id'=>$instance->customint1));
            if (!$cohort) {
                return get_string('pluginname', 'enrol_'.$enrol);
            }
            $cohortname = format_string($cohort->name, true, array('context'=>context::instance_by_id($cohort->contextid)));
            if ($role = $DB->get_record('role', array('id'=>$instance->roleid))) {
                $role = role_get_name($role, context_course::instance($instance->courseid, IGNORE_MISSING));
                return get_string('pluginname', 'enrol_'.$enrol) . ' (' . $cohortname . ' - ' . $role .')';
            } else {
                return get_string('pluginname', 'enrol_'.$enrol) . ' (' . $cohortname . ')';
            }

        } else {
            return format_string($instance->name, true, array('context'=>context_course::instance($instance->courseid)));
        }
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     * @param int $courseid
     * @return moodle_url page url
     */
    public function get_newinstance_link($courseid) {
        if (!$this->can_add_new_instances($courseid)) {
            return NULL;
        }
        // Multiple instances supported - multiple parent courses linked.
        return new moodle_url('/enrol/delayedcohort/edit.php', array('courseid'=>$courseid));
    }

    /**
     * Given a courseid this function returns true if the user is able to enrol or configure cohorts.
     * AND there are cohorts that the user can view.
     *
     * @param int $courseid
     * @return bool
     */
    protected function can_add_new_instances($courseid) {
        global $DB;

        $coursecontext = context_course::instance($courseid);
        if (!has_capability('moodle/course:enrolconfig', $coursecontext) or !has_capability('enrol/cohort:config', $coursecontext)) {
            return false;
        }
        list($sqlparents, $params) = $DB->get_in_or_equal($coursecontext->get_parent_context_ids());
        $sql = "
            SELECT
                id,
                contextid
            FROM
                {cohort}
            WHERE
                contextid $sqlparents
            ORDER BY
                name ASC
        ";
        $cohorts = $DB->get_records_sql($sql, $params);
        foreach ($cohorts as $c) {
            $context = context::instance_by_id($c->contextid);
            if (has_capability('moodle/cohort:view', $context)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns edit icons for the page with list of instances.
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        if ($instance->enrol !== 'delayedcohort') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid);

        $icons = array();

        if (has_capability('enrol/delayedcohort:config', $context)) {
            $editlink = new moodle_url("/enrol/delayedcohort/edit.php", array('courseid' => $instance->courseid, 'id' => $instance->id));
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('t/edit', get_string('edit'), 'core',
                    array('class' => 'iconsmall')));
        }

        return $icons;
    }

    /**
     * Update instance status
     *
     * @param stdClass $instance
     * @param int $newstatus ENROL_INSTANCE_ENABLED, ENROL_INSTANCE_DISABLED
     * @return void
     */
    public function update_status($instance, $newstatus) {
        global $CFG;

        parent::update_status($instance, $newstatus);

        require_once("$CFG->dirroot/enrol/delayedcohort/locallib.php");
        $trace = new null_progress_trace();
        enrol_delayedcohort_sync($trace, $instance->courseid);
        $trace->finished();
    }

    /**
     * Does this plugin allow manual unenrolment of a specific user?
     * Yes, but only if user suspended...
     *
     * @param stdClass $instance course enrol instance
     * @param stdClass $ue record from user_enrolments table
     *
     * @return bool - true means user with 'enrol/xxx:unenrol' may unenrol this user, false means
     * nobody may touch this user enrolment
     */
    public function allow_unenrol_user(stdClass $instance, stdClass $ue) {
        if ($ue->status == ENROL_USER_SUSPENDED) {
            return true;
        }

        return false;
    }

    /**
     * Gets an array of the user enrolment actions.
     *
     * @param course_enrolment_manager $manager
     * @param stdClass $ue A user enrolment object
     * @return array An array of user_enrolment_actions
     */
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue) {
        $actions = array();
        $context = $manager->get_context();
        $instance = $ue->enrolmentinstance;
        $params = $manager->get_moodlepage()->url->params();
        $params['ue'] = $ue->id;
        if ($this->allow_unenrol_user($instance, $ue) && has_capability('enrol/delayedcohort:unenrol', $context)) {
            $url = new moodle_url('/enrol/unenroluser.php', $params);
            $attrs = array('class' => 'unenrollink', 'rel' => $ue->id);
            $actions[] = new user_enrolment_action(new pix_icon('t/delete', ''), get_string('unenrol', 'enrol'), $url, $attrs);
        }
        return $actions;
    }

    /**
     * Returns a button to enrol a cohort or its users through the manual enrolment plugin.
     *
     * This function also adds a quickenrolment JS ui to the page so that users can be enrolled
     * via AJAX.
     *
     * @param course_enrolment_manager $manager
     * @return enrol_user_button
     */
    public function get_manual_enrol_button(course_enrolment_manager $manager) {
        $course = $manager->get_course();
        if (!$this->can_add_new_instances($course->id)) {
            return false;
        }

        $cohorturl = new moodle_url('/enrol/delayedcohort/edit.php', array('courseid' => $course->id));
        $button = new enrol_user_button($cohorturl, get_string('enrolcohort', 'enrol'), 'get');
        $button->class .= ' enrol_delayedcohort_plugin';

        $button->strings_for_js(array(
            'enrol',
            'synced',
            'enrolcohort',
            'enrolcohortusers',
            ), 'enrol');
        $button->strings_for_js(array(
            'ajaxmore',
            'cohortsearch',
            ), 'enrol_delayedcohort');
        $button->strings_for_js('assignroles', 'role');
        $button->strings_for_js('cohort', 'cohort');
        $button->strings_for_js('users', 'moodle');

        // No point showing this at all if the user cant manually enrol users.
        $hasmanualinstance = has_capability('enrol/manual:enrol', $manager->get_context()) && $manager->has_instance('manual');

        $modules = array('moodle-enrol_delayedcohort-quickenrolment', 'moodle-enrol_delayedcohort-quickenrolment-skin');
        $function = 'M.enrol_delayedcohort.quickenrolment.init';
        $arguments = array(
            'courseid' => $course->id,
            'ajaxurl' => '/enrol/delayedcohort/ajax.php',
            'url' => $manager->get_moodlepage()->url->out(false),
            'manualEnrolment' => $hasmanualinstance);
        $button->require_yui_module($modules, $function, array($arguments));

        return $button;
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB, $CFG;

        if (!$step->get_task()->is_samesite()) {
            // No cohort restore from other sites.
            $step->set_mapping('enrol', $oldid, 0);
            return;
        }

        if (!empty($data->customint2)) {
            $data->customint2 = $step->get_mappingid('group', $data->customint2);
        }

        if ($data->roleid and $DB->record_exists('cohort', array('id'=>$data->customint1))) {
            $params = array('roleid' => $data->roleid,
                            'customint1' => $data->customint1,
                            'courseid' => $course->id,
                            'enrol' => $this->get_name());
            $instance = $DB->get_record('enrol', $params);
            if ($instance) {
                $instanceid = $instance->id;
            } else {
                $instanceid = $this->add_instance($course, (array)$data);
            }
            $step->set_mapping('enrol', $oldid, $instanceid);

            require_once($CFG->dirroot.'/enrol/delayedcohort/locallib.php');
            $trace = new null_progress_trace();
            enrol_delayedcohort_sync($trace, $course->id);
            $trace->finished();

        } else if ($this->get_config('unenrolaction') == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
            $data->customint1 = 0;
            $params = array('roleid' => $data->roleid,
                            'customint1' => $data->customint1,
                            'courseid' => $course->id,
                            'enrol' => $this->get_name());
            $instance = $DB->get_record('enrol', $params);

            if ($instance) {
                $instanceid = $instance->id;
            } else {
                $data->status = ENROL_INSTANCE_DISABLED;
                $instanceid = $this->add_instance($course, (array)$data);
            }
            $step->set_mapping('enrol', $oldid, $instanceid);

            require_once($CFG->dirroot.'/enrol/delayedcohort/locallib.php');
            $trace = new null_progress_trace();
            enrol_delayedcohort_sync($trace, $course->id);
            $trace->finished();

        } else {
            $step->set_mapping('enrol', $oldid, 0);
        }
    }

    /**
     * Restore user enrolment.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $oldinstancestatus
     * @param int $userid
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid,
            $oldinstancestatus) {
        global $DB;

        if ($this->get_config('unenrolaction') != ENROL_EXT_REMOVED_SUSPENDNOROLES) {
            // Enrolments were already synchronised in restore_instance(), we do not want any suspended leftovers.
            return;
        }

        /*
         * ENROL_EXT_REMOVED_SUSPENDNOROLES means all previous enrolments are restored
         * but without roles and suspended.
         */

        if (!$DB->record_exists('user_enrolments', array('enrolid' => $instance->id, 'userid' => $userid))) {
            $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, ENROL_USER_SUSPENDED);
        }
    }
}

/**
 * Prevent removal of enrol roles.
 * @param int $itemid
 * @param int $groupid
 * @param int $userid
 * @return bool
 */
function enrol_delayedcohort_allow_group_member_remove($itemid, $groupid, $userid) {
    return false;
}
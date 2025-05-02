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
 * Delayed Cohort enrolment plugin.
 *
 * @package   enrol_delayedcohort
 * @category  enrol
 * @author    Valery Fremaux <valery.fremaux@gmail.com>
 * @copyright 2015 Valery Fremaux {@link http://www.mylearningfactory.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/enrol/delayedcohort/locallib.php');

/**
 * Cohort enrolment plugin implementation.
 * @author Valery Fremaux
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_delayedcohort_plugin extends enrol_plugin {

    const MAKE_NO_GROUP = 0;
    const MAKE_GROUP_FROM_COHORT = -1;
    const MAKE_GROUP_FROM_FORM = -2;

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
            $cohort = $DB->get_record('cohort', array('id' => $instance->customint1));
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
            return format_string($instance->name, true, array('context' => context_course::instance($instance->courseid)));
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
        return new moodle_url('/enrol/delayedcohort/edit.php', array('courseid' => $courseid));
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
     * Add new instance of enrol plugin, allow giving fields by real name.
     * @param object $course
     * @param array instance fields
     * @return int id of new instance, null if can not be created
     */
    public function add_instance($course, array $fields = NULL) {

        $fields = $this->preformat($fields);

        $instanceid = parent::add_instance($course, $fields);

        $this->setup_group($instanceid, $fields);

        return $instanceid;
    }

    /**
     * Check what has changed additionally
     * @param object $instance
     * @param object $data
     */
    public function update_instance($instance, $data) {

        $data = $this->preformat($data);

        if ($data['customint2'] != $instance->customint8) {
            // Group has changed, so renegotiate it.
            $this->clear_group($instance);
            $this->setup_group($instance->id, $data);
        }

        parent::update_instance($instance, $data);
    }

    protected function preformat(array $fields) {
        if (array_key_exists('cohortid', $fields)) {
            $fields['customint1'] = $fields['cohortid'];
            unset($fields['cohortid']);
        }

        if (array_key_exists('grouppropmode', $fields)) {
            $fields['customint2'] = $fields['grouppropmode'];
            unset($fields['grouppropode']);
        }

        if (array_key_exists('groupidnumber', $fields)) {
            $fields['customchar1'] = $fields['groupidnumber'];
            unset($fields['groupidnumber']);
        }

        if (array_key_exists('groupname', $fields)) {
            $fields['customchar2'] = $fields['groupname'];
            unset($fields['groupname']);
        }

        if (array_key_exists('legalstartdate', $fields)) {
            $fields['customint3'] = $fields['legalstartdate'];
            unset($fields['legalstartdate']);
        }

        if (array_key_exists('legalenddate', $fields)) {
            $fields['customint4'] = $fields['legalenddate'];
            unset($fields['legalenddate']);
        }
        
        return $fields;
    }

    /**
     * Additional process when deleting an instance.
     * @param object $instance
     */
    public function delete_instance($instance) {
        $this->clear_group($instance);
        parent::delete_instance($instance);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param int $instance
     * @return bool
     *
     */
    public function can_hide_show_instance($instance) {
        return true;
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
            $editlink = new moodle_url("/enrol/delayedcohort/edit.php", ['courseid' => $instance->courseid, 'id' => $instance->id]);
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('t/edit', get_string('edit'), 'core',
                    array('class' => 'iconsmall')));
        }

        return $icons;
    }

    /**
     * Called after updating/inserting course.
     *
     * @param bool $inserted true if course just inserted
     * @param stdClass $course
     * @param stdClass $data form data
     * @return void
     */
    public function course_updated($inserted, $course, $data) {
        assert(1);
        // It turns out there is no need for cohorts to deal with this hook, see MDL-34870.
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
        $button = new enrol_user_button($cohorturl, get_string('enrolcohort', 'enrol_delayedcohort'), 'get');
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

        if (!empty($data->customint2) && ($data->customint2 > 0)) {
            $data->customint2 = $step->get_mappingid('group', $data->customint2);
        }

        if ($data->roleid && $DB->record_exists('cohort', ['id' => $data->customint1])) {
            $params = [
                'roleid' => $data->roleid,
                'customint1' => $data->customint1,
                'courseid' => $course->id,
                'enrol' => $this->get_name(),
            ];
            $instance = $DB->get_record('enrol', $params);
            if ($instance) {
                $instanceid = $instance->id;
            } else {
                $instanceid = $this->add_instance($course, (array)$data);
            }
            $step->set_mapping('enrol', $oldid, $instanceid);

            $trace = new null_progress_trace();
            enrol_delayedcohort_sync($trace, $course->id);
            $trace->finished();

        } else if ($this->get_config('unenrolaction') == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
            $data->customint1 = 0;
            $params = [
                'roleid' => $data->roleid,
                'customint1' => $data->customint1,
                'courseid' => $course->id,
                'enrol' => $this->get_name(),
            ];
            $instance = $DB->get_record('enrol', $params);

            if ($instance) {
                $instanceid = $instance->id;
            } else {
                $data->status = ENROL_INSTANCE_DISABLED;
                $instanceid = $this->add_instance($course, (array)$data);
            }
            $step->set_mapping('enrol', $oldid, $instanceid);

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

    /**
     * Restore user group membership.
     * @param stdClass $instance
     * @param int $groupid
     * @param int $userid
     */
    public function restore_group_member($instance, $groupid, $userid) {
        // Nothing to do here, the group members are added in $this->restore_group_restored()
        return;
    }

    /**
     * Return information for enrolment instance containing list of parameters required
     * for enrolment, name of enrolment plugin etc.
     *
     * @param stdClass $instance enrolment instance
     * @return stdClass instance info.
     * @since Moodle 3.1
     */
    public function get_enrol_info(stdClass $instance) {

        $instanceinfo = new stdClass();
        $instanceinfo->id = $instance->id;
        $instanceinfo->courseid = $instance->courseid;
        $instanceinfo->cohortid = $instance->customint1; // Cohort id
        $instanceinfo->grouppropmode = $instance->customint2; // Group propagation mode
        $instanceinfo->roleid = $instance->roleid; // role
        $instanceinfo->enrolstartdate = $instance->enrolstartdate;
        $instanceinfo->enrolenddate = $instance->enrolenddate;
        $instanceinfo->legalstartdate = $instance->customint3;
        $instanceinfo->legalenddate = $instance->customint4;
        $instanceinfo->groupidnumber = $instance->customchar1;
        $instanceinfo->groupname = $instance->customchar2;
        $instanceinfo->type = $this->get_name();
        $instanceinfo->name = $this->get_instance_name($instance);
        $instanceinfo->status = $instance->status == ENROL_INSTANCE_ENABLED;

        return $instanceinfo;
    }

    /**
     * This creates the course group to sync to enrols. Customint8
     * will be used to track actually bound group to the enrol methofd.
     * @param array $fields
     */
    public function setup_group($instanceid, $fields) {
        global $DB;

        $oldgroup = $DB->get_field('enrol', 'customint8', ['id' => $instanceid]);

        switch ($fields['customint2']) {
            case self::MAKE_NO_GROUP:
                // No more group to sync. So delete everything that is bound.
                if ($oldgroup) {
                    groups_delete_group($oldgroup);
                    $DB->set_field('enrol', 'customint8', 0, ['id' => $instanceid]);
                }
                return;
            case self::MAKE_GROUP_FROM_COHORT: {
                // Make group from cohort info stored in customint1
                $cohort = $DB->get_record('cohort', ['id' => $fields['customint1']]);
                if (!$cohort) {
                    // cohort has gone away in the meanwhile...
                    return;
                }
                $group = new StdClass;
                $group->name = $cohort->name;
                $group->courseid = $fields['courseid'];
                $group->idnumber = 'AUTO-'.$cohort->idnumber;
                $groupid = groups_create_group($group);
                $DB->set_field('enrol', 'customint8', $groupid, ['id' => $instanceid]);
                return;
            }
            case self::MAKE_GROUP_FROM_FORM: {
                // Make group from form (fields) info
                $group = new StdClass;
                $group->courseid = $fields['courseid'];
                $group->name = $fields['customchar2'];
                $group->idnumber = 'AUTO-'.$fields['customchar1'];
                $groupid = groups_create_group($group);
                $DB->set_field('enrol', 'customint8', $groupid, ['id' => $instanceid]);
                return;
            }
            default: {
                // All cases > 0. Register the group internally so we can memoize previous state.
                $DB->set_field('enrol', 'customint8', $fields['customint2'], ['id' => $instanceid]);
            }
        }

        // Users should be added to the group on sync.
    }

    /**
     * Deletes an automatically generated group from the delayedcohort
     * instance setup. DO NOT delete other groups, even if bound to
     * the enrol instance.
     * @param object $instance
     */
    public function clear_group($instance) {
        global $DB;

        if (!empty($instance->customint8)) {
            $groupidnum = $DB->get_field('groups', 'idnumber', ['id' => $instance->customint8]);
            if (preg_match('/^AUTO-/', $groupidnum)) {
                groups_delete_group($instance->customint8);
            }
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
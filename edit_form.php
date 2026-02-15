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
 * Adds instance form
 *
 * @package enrol_delayedcohort
 * @category  enrol
 * @author Valery Fremaux <valery.fremaux@gmail.com>
 * @copyright 2015 Valery Fremaux {@link http://www.mylearningfactory.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

class enrol_delayedcohort_edit_form extends moodleform {

    public function definition() {
        global $DB;

        $mform  = $this->_form;

        list($instance, $plugin, $course) = $this->_customdata;
        $coursecontext = context_course::instance($course->id);

        $enrol = enrol_get_plugin('delayedcohort');

        $mform->addElement('hidden', 'return');
        $mform->setType('return', PARAM_TEXT);

        $mform->addElement('hidden', 'category');
        $mform->setType('category', PARAM_INT);

        $mform->addElement('header','general', get_string('pluginname', 'enrol_delayedcohort'));

        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'), 'size="80"');
        $mform->setType('name', PARAM_TEXT);

        $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                         ENROL_INSTANCE_DISABLED => get_string('no'));
        $mform->addElement('select', 'status', get_string('status', 'enrol_delayedcohort'), $options);

        // Choose cohort. customint1
        if ($instance->id) {
            if ($cohort = $DB->get_record('cohort', ['id' => $instance->customint1])) {
                $options = array('context' => context::instance_by_id($cohort->contextid));
                $cohorts = array($instance->customint1 => format_string($cohort->name, true, $options));
            } else {
                $cohorts = array($instance->customint1 => get_string('error'));
            }
            $mform->addElement('select', 'customint1', get_string('cohort', 'cohort'), $cohorts);
            $mform->setConstant('customint1', $instance->customint1);
            $mform->hardFreeze('customint1', $instance->customint1);

        } else {
            $cohorts = array('' => get_string('choosedots'));
            list($sqlparents, $params) = $DB->get_in_or_equal($coursecontext->get_parent_context_ids());
            $sql = "SELECT id, name, idnumber, contextid
                      FROM {cohort}
                     WHERE contextid $sqlparents
                  ORDER BY name ASC, idnumber ASC";
            $rs = $DB->get_recordset_sql($sql, $params);

            foreach ($rs as $c) {
                $context = context::instance_by_id($c->contextid);
                if (!has_capability('moodle/cohort:view', $context)) {
                    continue;
                }
                $cohorts[$c->id] = format_string($c->name);
            }
            $rs->close();
            $mform->addElement('select', 'customint1', get_string('cohort', 'cohort'), $cohorts);
            $mform->addRule('customint1', get_string('required'), 'required', null, 'client');
        }

        // Choose role. roleid.
        $roles = get_assignable_roles($coursecontext);
        $roles[0] = get_string('none');
        $roles = array_reverse($roles, true); // Descending default sortorder.
        $mform->addElement('select', 'roleid', get_string('assignrole', 'enrol_delayedcohort'), $roles);
        $mform->setDefault('roleid', $enrol->get_config('roleid'));

        if ($instance->id && !isset($roles[$instance->roleid])) {
            if ($role = $DB->get_record('role', array('id' => $instance->roleid))) {
                $roles = role_fix_names($roles, $coursecontext, ROLENAME_ALIAS, true);
                $roles[$instance->roleid] = role_get_name($role, $coursecontext);
            } else {
                $roles[$instance->roleid] = get_string('error');
            }
        }

        // Choose group propagation mode.
        $groups = [
            enrol_delayedcohort_plugin::MAKE_NO_GROUP => get_string('none'),
            enrol_delayedcohort_plugin::MAKE_GROUP_FROM_COHORT => get_string('createfromcohort', 'enrol_delayedcohort'),
            enrol_delayedcohort_plugin::MAKE_GROUP_FROM_FORM => get_string('createfromform', 'enrol_delayedcohort'),
        ];
        foreach (groups_get_all_groups($course->id) as $group) {
            $groups[$group->id] = format_string($group->name, true, ['context' => $coursecontext]);
        }
        $mform->addElement('select', 'customint2', get_string('addgroup', 'enrol_delayedcohort'), $groups);
        $mform->addHelpButton('customint2', 'grouppropmode', 'enrol_delayedcohort');

        // Group idnumber. customchar1.
        $label = get_string('groupidnumber', 'enrol_delayedcohort');
        $mform->addElement('text', 'customchar1', $label, '', ['size' => 32]);
        $mform->setType('customchar1', PARAM_TEXT);
        $mform->addHelpButton('customchar1', 'groupidnumber', 'enrol_delayedcohort');
        $mform->disabledIf('customchar1', 'customint2', 'neq', -2);

        // Group name. customchar2.
        $label = get_string('groupname', 'enrol_delayedcohort');
        $mform->addElement('text', 'customchar2', $label, '', ['size' => 64]);
        $mform->setType('customchar2', PARAM_TEXT);
        $mform->disabledIf('customchar2', 'customint2', 'neq', -2);

        // Trigger date, eq to enrolstartdate.
        $label = get_string('triggerdate', 'enrol_delayedcohort');
        $mform->addElement('date_time_selector', 'enrolstartdate', $label, ['optional' => true]);
        $mform->addHelpButton('enrolstartdate', 'enrolstartdate', 'enrol_delayedcohort');

        // enrolenddate.
        $label = get_string('enddate', 'enrol_delayedcohort');
        $mform->addElement('date_time_selector', 'enrolenddate', $label, ['optional' => true]);
        $mform->addHelpButton('enrolenddate', 'enrolenddate', 'enrol_delayedcohort');

        // legal start date. customint3.
        $label = get_string('legalstartdate', 'enrol_delayedcohort');
        $mform->addElement('date_time_selector', 'customint3', $label, ['optional' => true]);
        $mform->addHelpButton('customint3', 'legaldate', 'enrol_delayedcohort');

        // legal end date. customint4.
        $label = get_string('legalenddate', 'enrol_delayedcohort');
        $mform->addElement('date_time_selector', 'customint4', $label, ['optional' => true]);
        $mform->addHelpButton('customint4', 'legaldate', 'enrol_delayedcohort');

        $mform->addElement('hidden', 'courseid', null);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'id', null);
        $mform->setType('id', PARAM_INT);

        if ($instance->id) {
            $this->add_action_buttons(true);
        } else {
            $this->add_action_buttons(true, get_string('addinstance', 'enrol'));
        }

        $this->set_data($instance);
    }

    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        $params = [
            'roleid' => $data['roleid'],
            'customint1' => $data['customint1'],
            'courseid' => $data['courseid'],
            'id' => $data['id'],
        ];
        $select = "
            roleid = :roleid AND
            customint1 = :customint1 AND
            courseid = :courseid AND
            enrol = 'delayedcohort' AND
            id <> :id
        ";
        if ($DB->record_exists_select('enrol', $select, $params)) {
            $errors['roleid'] = get_string('instanceexists', 'enrol_delayedcohort');
        }

        return $errors;
    }
}

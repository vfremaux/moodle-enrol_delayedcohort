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
 * External enrol delayed cohort api.
 *
 * This api is mostly read only, the actual enrol and unenrol
 * support is in each enrol plugin.
 *
 * @package    enrol_delayedcohort
 * @category   external
 * @copyright  2023 Valery Fremaux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

/**
 * Manual enrolment external functions.
 *
 * @package    enrol_delayed cohort
 * @category   external
 * @copyright  2011 Valery Fremaux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_delayedcohort_external extends external_api {

    /**
     * Returns description of get_instances() parameters.
     *
     * @return external_function_parameters
     */
    public static function get_instances_parameters() {
        return new external_function_parameters(
            [
                'coursefield' => new external_value(PARAM_ALPHA, 'Primary idenfier field for course, id, idnumber or shortname'),
                'courseid' => new external_value(PARAM_TEXT, 'idenfier value for course'),
            ]
        );
    }

    /**
     * Return guest enrolment instances in a course.
     *
     * @param int $coursefield identifier field for the course.
     * @param int $courseid identifier value for the course.
     * @return array enrol method instances short info (id and status).
     */
    public static function get_instances($coursefield, $courseid) {
        global $DB;

        $params = self::validate_parameters(self::get_instances_parameters(), ['coursefield' => $coursefield, 'courseid' => $courseid]);

        // Retrieve guest enrolment plugin.
        $enrolplugin = enrol_get_plugin('delayedcohort');
        if (empty($enrolplugin)) {
            throw new moodle_exception('invaliddata', 'error');
        }

        switch($params['coursefield']) {
            case 'id': {
                $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
                break;
            }
            case 'idnumber': {
                $course = $DB->get_record('course', ['idnumber' => $params['courseid']], '*', MUST_EXIST);
                break;
            }
            case 'shortname': {
                $course = $DB->get_record('course', ['shortname' => $params['courseid']], '*', MUST_EXIST);
                break;
            }
            default:
                throw new moodle_exception('invaliddata', 'error');
        }

        $context = context_course::instance($course->id);
        if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context)) {
            throw new moodle_exception('coursehidden');
        }

        // Turns status to positive logic, i.e. true if enabled.
        $fields = 'id,enrol as type,name,courseid,enrolstartdate,customint1 as cohortid,status';
        $enrolinstances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'delayedcohort'], 'id', $fields);
        foreach($enrolinstances as &$ei) {
            $ei->status = !$ei->status; // invert logic vs database.
        }

        return $enrolinstances;
    }

    /**
     * Returns description of get_instances() result value.
     *
     * @return external_description
     */
    public static function get_instances_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                [
                    'id' => new external_value(PARAM_INT, 'Id of course enrolment instance'),
                    'name' => new external_value(PARAM_RAW, 'Name of enrolment plugin'),
                    'type' => new external_value(PARAM_RAW, 'Type of enrolment plugin'),
                    'courseid' => new external_value(PARAM_INT, 'Course id'),
                    'enrolstartdate' => new external_value(PARAM_INT, 'Enrol start date'),
                    'cohortid' => new external_value(PARAM_INT, 'Cohort id'),
                    'status' => new external_value(PARAM_BOOL, 'Is the enrolment enabled?'),
                ]
            )
        );
    }

    /**
     * Returns description of get_instance_info() parameters.
     *
     * @return external_function_parameters
     */
    public static function get_instance_info_parameters() {
        return new external_function_parameters(
            ['instanceid' => new external_value(PARAM_INT, 'Instance id of delayedcohort enrolment plugin.')]
        );
    }

    /**
     * Return guest enrolment instance information.
     *
     * @param int $instanceid instance id of guest enrolment plugin.
     * @return array warnings and instance information.
     */
    public static function get_instance_info($instanceid) {
        global $DB;

        $params = self::validate_parameters(self::get_instance_info_parameters(), ['instanceid' => $instanceid]);
        $warnings = [];

        // Retrieve guest enrolment plugin.
        $enrolplugin = enrol_get_plugin('delayedcohort');
        if (empty($enrolplugin)) {
            throw new moodle_exception('invaliddata', 'error');
        }

        self::validate_context(context_system::instance());
        $enrolinstance = $DB->get_record('enrol', ['id' => $params['instanceid']], '*', MUST_EXIST);

        $course = $DB->get_record('course', ['id' => $enrolinstance->courseid], '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context)) {
            throw new moodle_exception('coursehidden');
        }

        $instanceinfo = $enrolplugin->get_enrol_info($enrolinstance);

        unset($instanceinfo->requiredparam);

        $result = [];
        $result['instanceinfo'] = $instanceinfo;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of get_instance_info() result value.
     *
     * @return external_description
     */
    public static function get_instance_info_returns() {
        return new external_single_structure(
            [
                'instanceinfo' => new external_single_structure(
                    [
                        'id' => new external_value(PARAM_INT, 'Id of course enrolment instance'),
                        'courseid' => new external_value(PARAM_INT, 'Id of course'),
                        'type' => new external_value(PARAM_PLUGIN, 'Type of enrolment plugin'),
                        'name' => new external_value(PARAM_RAW, 'Name of enrolment plugin'),
                        'enrolstartdate' => new external_value(PARAM_INT, 'Enrol start date'),
                        'enrolenddate' => new external_value(PARAM_INT, 'Enrol end date'),
                        'roleid' => new external_value(PARAM_INT, 'Role id'),
                        'cohortid' => new external_value(PARAM_INT, 'Cohort id'),
                        'grouppropmode' => new external_value(PARAM_INT, 'Group propagation mode'),
                        'legalstartdate' => new external_value(PARAM_INT, 'Legal start date'),
                        'legalenddate' => new external_value(PARAM_INT, 'Legal end date'),
                        'groupidnumber' => new external_value(PARAM_TEXT, 'Group idnumber'),
                        'groupname' => new external_value(PARAM_TEXT, 'Group name'),
                        'status' => new external_value(PARAM_BOOL, 'Is the enrolment enabled?'),
                    ]
                ),
                'warnings' => new external_warnings(),
            ]
        );
    }

    /**
     * Returns description of add_method_to_course() parameters.
     *
     * @return external_function_parameters
     */
    public static function add_method_to_course_parameters() {
        return new external_function_parameters(
            [
                'coursefield' => new external_value(PARAM_ALPHA, 'Primary idenfier field for course, id, idnumber or shortname'),
                'cohortfield' => new external_value(PARAM_ALPHA, 'Field to identify the cohort, id or idnumber'),
                'rolefield' => new external_value(PARAM_ALPHA, 'field to identify the role, id or shortname'),
                'courseid' => new external_value(PARAM_TEXT, 'identifier value for course'),
                'instance' => new external_single_structure(
                    [
                        'name' => new external_value(PARAM_TEXT, 'Name of the instance'),
                        'cohortid' => new external_value(PARAM_TEXT, 'id of the cohort, id or idnumber'),
                        'roleid' => new external_value(PARAM_TEXT, 'identifier of the role'),
                        'grouppropmode' => new external_value(PARAM_TEXT, 'Group sync mode'),
                        'enrolstartdate' => new external_value(PARAM_INT, 'Start of enrolment'),
                        'enrolenddate' => new external_value(PARAM_INT, 'End of enrolment'),
                        'legalstartdate' => new external_value(PARAM_INT, 'Legal start of enrolment (for reports)'),
                        'legalenddate' => new external_value(PARAM_INT, 'Legal end of enrolment (for reports)'),
                        'groupidnumber' => new external_value(PARAM_INT, 'Group idnumber', VALUE_OPTIONAL),
                        'groupname' => new external_value(PARAM_INT, 'Group name', VALUE_OPTIONAL),
                        'status' => new external_value(PARAM_INT, 'Initial status'),
                    ]
                )
            ]
        );
    }

    /**
     * Return guest enrolment instances in a course.
     *
     * @param int $coursefield identifier field for the course.
     * @param int $cohortfield identifier field for the cohort.
     * @param int $rolefield identifier field for the role.
     * @param int $courseid identifier value for the course.
     * @return array enrol method instance info (all fields).
     */
    public static function add_method_to_course($coursefield, $cohortfield, $rolefield, $courseid, $instance) {
        global $DB;

        $input = [
            'coursefield' => $coursefield,
            'cohortfield' => $cohortfield,
            'rolefield' => $rolefield,
            'courseid' => $courseid,
            'instance' => $instance,
        ];
        $params = self::validate_parameters(self::add_method_to_course_parameters(), $input);

        // Retrieve guest enrolment plugin.
        $enrolplugin = enrol_get_plugin('delayedcohort');
        if (empty($enrolplugin)) {
            throw new moodle_exception('invaliddata', 'error');
        }

        switch($params['coursefield']) {
            case 'id': {
                $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
                break;
            }
            case 'idnumber': {
                $course = $DB->get_record('course', ['idnumber' => $params['courseid']], '*', MUST_EXIST);
                break;
            }
            case 'shortname': {
                $course = $DB->get_record('course', ['shortname' => $params['courseid']], '*', MUST_EXIST);
                break;
            }
            default:
                throw new moodle_exception('invaliddata', 'error');
        }

        switch($params['rolefield']) {
            case 'id': {
                break;
            }
            case 'shortname': {
                $roleid = $DB->get_field('role', 'id', ['shortname' => $params['instance']['roleid']], MUST_EXIST);
                $params['instance']['roleid'] = $roleid;
                break;
            }
        }
        unset($params['rolefield']);

        switch($params['cohortfield']) {
            case 'id': {
                break;
            }
            case 'idnumber': {
                $cohortid = $DB->get_field('cohort', 'id', ['idnumber' => $params['instance']['cohortid']], MUST_EXIST);
                $params['instance']['cohortid'] = $cohortid;
                break;
            }
        }
        unset($params['cohortfield']);

        $context = context_course::instance($course->id);
        if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context)) {
            throw new moodle_exception('coursehidden');
        }

        if ($oldenrol = $DB->get_record('enrol', ['courseid' => $course->id, 'customint1' => $params['instance']['cohortid']])) {
            $enrolplugin->update_instance($oldenrol, $params['instance']);
            $enrolid = $oldenrol->id;
        } else {
            $enrolid = $enrolplugin->add_instance($course, $params['instance']);
        }

        $params = [
            'context' => context_course::instance($course->id),
            'objectid' => $enrolid,
            'other' => [
                'courseid' => $course->id,
            ],
        ];
        $event = \enrol_delayedcohort\event\delayedcohort_created::create($params);
        $event->trigger();

        return self::get_instance_info($enrolid);
    }

    /**
     * Returns description of add_method_to_course() result value.
     *
     * @return external_description
     */
    public static function add_method_to_course_returns() {
        return self::get_instance_info_returns();
    }

    /**
     * Returns description of add_method_to_course() parameters.
     *
     * @return external_function_parameters
     */
    public static function delete_method_instance_parameters() {
        return new external_function_parameters(
            [
                'coursefield' => new external_value(PARAM_ALPHA, 'Primary idenfier field for course, id, idnumber or shortname'),
                'cohortfield' => new external_value(PARAM_ALPHA, 'Field to identify the cohort, id or idnumber'),
                'courseid' => new external_value(PARAM_TEXT, 'idenfier value for course'),
                'cohortid' => new external_value(PARAM_TEXT, 'id of the cohort, id or idnumber'),
            ]
        );
    }

    /**
     * Return guest enrolment instances in a course.
     *
     * @param int $coursefield identifier field for the course.
     * @param int $cohortfield identifier field for the cohort.
     * @param int $rolefield identifier field for the role.
     * @param int $courseid identifier value for the course.
     * @return void
     */
    public static function delete_method_instance($coursefield, $cohortfield, $courseid, $cohortid) {
        global $DB;

        $input = [
            'coursefield' => $coursefield,
            'cohortfield' => $cohortfield,
            'courseid' => $courseid,
            'cohortid' => $cohortid,
        ];
        $params = self::validate_parameters(self::delete_method_instance_parameters(), $input);

        // Retrieve guest enrolment plugin.
        $enrolplugin = enrol_get_plugin('delayedcohort');
        if (empty($enrolplugin)) {
            throw new moodle_exception('invaliddata', 'error');
        }

        switch($params['coursefield']) {
            case 'id': {
                $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
                break;
            }
            case 'idnumber': {
                $course = $DB->get_record('course', ['idnumber' => $params['courseid']], '*', MUST_EXIST);
                break;
            }
            case 'shortname': {
                $course = $DB->get_record('course', ['shortname' => $params['courseid']], '*', MUST_EXIST);
                break;
            }
            default:
                throw new moodle_exception('invaliddata', 'error');
        }

        switch($params['cohortfield']) {
            case 'id': {
                $cohortid = $params['cohortid'];
                break;
            }
            case 'idnumber': {
                $cohortid = $DB->get_field('cohort', 'id', ['idnumber' => $params['cohortid']], MUST_EXIST);
                break;
            }
        }

        $instance = $DB->get_record('enrol', ['enrol' => 'delayedcohort', 'courseid' => $course->id, 'customint1' => $cohortid], '*', MUST_EXIST);
        $enrolplugin->delete_instance($instance);

        $params = [
            'context' => context_course::instance($course->id),
            'objectid' => $instance->id,
            'other' => [
                'courseid' => $course->id,
            ],
        ];
        $event = \enrol_delayedcohort\event\delayedcohort_deleted::create($params);
        $event->add_record_snapshot('enrol', $instance);
        $event->trigger();

        return null;
    }

    /**
     * Returns void.
     *
     * @return external_description
     */
    public static function delete_method_instance_returns() {
        return null;
    }


    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function get_enrollable_users_parameters() {
        return new external_function_parameters(
            [
                'coursefield' => new external_value(PARAM_RAW, 'Field for course identification, id, shortname or idnumber'),
                'courseid' => new external_value(PARAM_TEXT, 'course id'),
                'enrolid' => new external_value(PARAM_INT, 'enrol instance id'),
                'options'  => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'name'  => new external_value(PARAM_ALPHANUMEXT, 'option name'),
                            'value' => new external_value(PARAM_RAW, 'option value')
                        ]
                    ), 'Option names:
                            * withcapability (string) return only users with this capability. This option requires \'moodle/role:review\' on the course context.
                            * groupid (integer) return only users in this group id. If the course has groups enabled and this param
                                                isn\'t defined, returns all the viewable users.
                                                This option requires \'moodle/site:accessallgroups\' on the course context if the
                                                user doesn\'t belong to the group.
                            * onlyactive (integer) return only users with active enrolments and matching time restrictions.
                                                This option requires \'moodle/course:enrolreview\' on the course context.
                                                Please note that this option can\'t
                                                be used together with onlysuspended (only one can be active).
                            * onlysuspended (integer) return only suspended users. This option requires
                                            \'moodle/course:enrolreview\' on the course context. Please note that this option can\'t
                                                be used together with onlyactive (only one can be active).
                            * userfields (\'string, string, ...\') return only the values of these user fields.
                            * limitfrom (integer) sql limit from.
                            * limitnumber (integer) maximum number of returned users.
                            * sortby (string) sort by id, firstname or lastname. For ordering like the site does, use siteorder.
                            * sortdirection (string) ASC or DESC',
                            VALUE_DEFAULT, []),
            ]
        );
    }

    /**
     * Get enrollable users.
     *
     * @param array $coursefield field for course identification
     * @param array $courseid field for course identification
     * @param array $options user filter and field options
     * @throws coding_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     */
    public static function get_enrollable_users($coursefield, $courseid, $enrolid = 0, $options = []) {
        global $CFG, $USER, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . "/user/lib.php");

        $params = self::validate_parameters(
            self::get_enrollable_users_parameters(),
            [
                'coursefield' => $coursefield,
                'courseid' => $courseid,
                'enrolid' => $enrolid,
                'options' => $options,
            ]
        );
        $withcapability = '';
        $groupid        = 0;
        $onlyactive     = false;
        $onlysuspended  = false;
        $userfields     = [];
        $limitfrom = 0;
        $limitnumber = 0;
        $sortby = 'u.id';
        $sortparams = [];
        $sortdirection = 'ASC';
        foreach ($options as $option) {
            switch ($option['name']) {
                case 'withcapability':
                    $withcapability = $option['value'];
                    break;
                case 'groupid':
                    $groupid = (int)$option['value'];
                    break;
                case 'onlyactive':
                    $onlyactive = !empty($option['value']);
                    break;
                case 'onlysuspended':
                    $onlysuspended = !empty($option['value']);
                    break;
                case 'userfields':
                    $thefields = explode(',', $option['value']);
                    foreach ($thefields as $f) {
                        $userfields[] = clean_param($f, PARAM_ALPHANUMEXT);
                    }
                    break;
                case 'limitfrom' :
                    $limitfrom = clean_param($option['value'], PARAM_INT);
                    break;
                case 'limitnumber' :
                    $limitnumber = clean_param($option['value'], PARAM_INT);
                    break;
                case 'sortby':
                    $sortallowedvalues = ['id', 'firstname', 'lastname', 'siteorder'];
                    if (!in_array($option['value'], $sortallowedvalues)) {
                        throw new invalid_parameter_exception('Invalid value for sortby parameter (value: ' .
                            $option['value'] . '), allowed values are: ' . implode(',', $sortallowedvalues));
                    }
                    if ($option['value'] == 'siteorder') {
                        list($sortby, $sortparams) = users_order_by_sql('u');
                    } else {
                        $sortby = 'u.' . $option['value'];
                    }
                    break;
                case 'sortdirection':
                    $sortdirection = strtoupper($option['value']);
                    $directionallowedvalues = ['ASC', 'DESC'];
                    if (!in_array($sortdirection, $directionallowedvalues)) {
                        throw new invalid_parameter_exception('Invalid value for sortdirection parameter
                        (value: ' . $sortdirection . '),' . 'allowed values are: ' . implode(',', $directionallowedvalues));
                    }
                    break;
            }
        }

        switch($params['coursefield']) {
            case 'id':
                $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
                break;
            case 'idnumber':
                $course = $DB->get_record('course', ['idnumber' => $params['courseid']], '*', MUST_EXIST);
                break;
            case 'shortname':
                $course = $DB->get_record('course', ['shortname' => $params['courseid']], '*', MUST_EXIST);
                break;
            default:
                throw new moodle_exception('invaliddata' , 'error');
        }
        $coursecontext = context_course::instance($course->id, IGNORE_MISSING);
        if ($courseid == SITEID) {
            $context = context_system::instance();
        } else {
            $context = $coursecontext;
        }
        try {
            self::validate_context($context);
        } catch (Exception $e) {
            $exceptionparam = new stdClass();
            $exceptionparam->message = $e->getMessage();
            $exceptionparam->coursefield = $params['coursefield'];
            $exceptionparam->courseid = $params['courseid'];
            throw new moodle_exception('errorcoursecontextnotvalid' , 'webservice', '', $exceptionparam);
        }

        course_require_view_participants($context);

        // to overwrite this parameter, you need role:review capability
        if ($withcapability) {
            require_capability('moodle/role:review', $coursecontext);
        }
        // need accessallgroups capability if you want to overwrite this option
        if (!empty($groupid) && !groups_is_member($groupid)) {
            require_capability('moodle/site:accessallgroups', $coursecontext);
        }
        // to overwrite this option, you need course:enrolereview permission
        if ($onlyactive || $onlysuspended) {
            require_capability('moodle/course:enrolreview', $coursecontext);
        }

        $plugin = enrol_get_plugin('delayedcohort');

        if ($enrolid) {
            // Redundant params, by secures misrecordings.
            $params = ['courseid' => $course->id, 'id' => $enrolid, 'enrol' => 'delayedcohort'];
        } else {
            $params = ['courseid' => $course->id, 'enrol' => 'delayedcohort'];
        }
        $enrolinstances = $DB->get_records('enrol', $params);

        $enrolledparams = [];

        $cohortids = [];
        foreach ($enrolinstances as $enrol) {
            $cohortids[] = $enrol->customint1;
        }
        list($cohortsql, $cohortparams) = $DB->get_in_or_equal($cohortids, SQL_PARAMS_NAMED);
        $cohortjoin = "
            JOIN
                {cohort_members} cm
            ON
                (u.id = cm.userid AND
                cm.cohortid $cohortsql)
        ";
        $enrolledparams = array_merge($enrolledparams, $cohortparams);

        $groupjoin = '';
        if (empty($groupid) && groups_get_course_groupmode($course) == SEPARATEGROUPS &&
                !has_capability('moodle/site:accessallgroups', $coursecontext)) {
            // Filter by groups the user can view.
            $usergroups = groups_get_user_groups($course->id);
            if (!empty($usergroups['0'])) {
                list($groupsql, $groupparams) = $DB->get_in_or_equal($usergroups['0'], SQL_PARAMS_NAMED);
                $groupjoin = "
                    JOIN
                        {groups_members} gm
                    ON
                        (u.id = gm.userid AND
                        gm.groupid $groupsql)
                ";
                $enrolledparams = array_merge($enrolledparams, $groupparams);
            } else {
                // User doesn't belong to any group, so he can't see any user. Return an empty array.
                return [];
            }
        }
        $sql = "
            SELECT
                u.*,
                COALESCE(ul.timeaccess, 0) AS lastcourseaccess
            FROM
                {user} u
            $cohortjoin
            $groupjoin
            LEFT JOIN
                {user_lastaccess} ul
            ON
                (ul.userid = u.id AND
                ul.courseid = :courseid)
            ORDER BY
                $sortby
                $sortdirection
        ";
        $enrolledparams = array_merge($enrolledparams, $sortparams);
        $enrolledparams['courseid'] = $courseid;

        $enrolledusers = $DB->get_recordset_sql($sql, $enrolledparams, $limitfrom, $limitnumber);
        $users = [];
        foreach ($enrolledusers as $user) {
            context_helper::preload_from_record($user);
            if ($userdetails = user_get_user_details($user, $course, $userfields)) {
                // Rewrite everything, too messy...
                if (is_enrolled($coursecontext, $user)) {
                    $userdetails['enrolledcourses'] = [['id' => $course->id, 'shortname' => $course->shortname, 'fullname' => $course->fullname]];
                } else {
                    $userdetails['enrolledcourses'] = [];
                }
                $users[] = $userdetails;
            }
        }
        $enrolledusers->close();

        return $users;
    }

    /**
     * Returns description of method result value.
     *
     * @return null
     */
    public static function get_enrollable_users_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                [
                    'id'    => new external_value(PARAM_INT, 'ID of the user'),
                    'username'    => new external_value(PARAM_RAW, 'Username policy is defined in Moodle security config', VALUE_OPTIONAL),
                    'firstname'   => new external_value(PARAM_NOTAGS, 'The first name(s) of the user', VALUE_OPTIONAL),
                    'lastname'    => new external_value(PARAM_NOTAGS, 'The family name of the user', VALUE_OPTIONAL),
                    'fullname'    => new external_value(PARAM_NOTAGS, 'The fullname of the user'),
                    'email'       => new external_value(PARAM_TEXT, 'An email address - allow email as root@localhost', VALUE_OPTIONAL),
                    'address'     => new external_value(PARAM_TEXT, 'Postal address', VALUE_OPTIONAL),
                    'phone1'      => new external_value(PARAM_NOTAGS, 'Phone 1', VALUE_OPTIONAL),
                    'phone2'      => new external_value(PARAM_NOTAGS, 'Phone 2', VALUE_OPTIONAL),
                    'department'  => new external_value(PARAM_TEXT, 'department', VALUE_OPTIONAL),
                    'institution' => new external_value(PARAM_TEXT, 'institution', VALUE_OPTIONAL),
                    'idnumber'    => new external_value(PARAM_RAW, 'An arbitrary ID code number perhaps from the institution', VALUE_OPTIONAL),
                    'interests'   => new external_value(PARAM_TEXT, 'user interests (separated by commas)', VALUE_OPTIONAL),
                    'firstaccess' => new external_value(PARAM_INT, 'first access to the site (0 if never)', VALUE_OPTIONAL),
                    'lastaccess'  => new external_value(PARAM_INT, 'last access to the site (0 if never)', VALUE_OPTIONAL),
                    'lastcourseaccess'  => new external_value(PARAM_INT, 'last access to the course (0 if never)', VALUE_OPTIONAL),
                    'description' => new external_value(PARAM_RAW, 'User profile description', VALUE_OPTIONAL),
                    'descriptionformat' => new external_format_value('description', VALUE_OPTIONAL),
                    'city'        => new external_value(PARAM_NOTAGS, 'Home city of the user', VALUE_OPTIONAL),
                    'country'     => new external_value(PARAM_ALPHA, 'Home country code of the user, such as AU or CZ', VALUE_OPTIONAL),
                    'profileimageurlsmall' => new external_value(PARAM_URL, 'User image profile URL - small version', VALUE_OPTIONAL),
                    'profileimageurl' => new external_value(PARAM_URL, 'User image profile URL - big version', VALUE_OPTIONAL),
                    'customfields' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'type'  => new external_value(PARAM_ALPHANUMEXT, 'The type of the custom field - text field, checkbox...'),
                                'value' => new external_value(PARAM_RAW, 'The value of the custom field'),
                                'name' => new external_value(PARAM_RAW, 'The name of the custom field'),
                                'shortname' => new external_value(PARAM_RAW, 'The shortname of the custom field - to be able to build the field class in the code'),
                            ]
                        ), 'User custom fields (also known as user profil fields)', VALUE_OPTIONAL),
                    'groups' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'id'  => new external_value(PARAM_INT, 'group id'),
                                'name' => new external_value(PARAM_RAW, 'group name'),
                                'description' => new external_value(PARAM_RAW, 'group description'),
                                'descriptionformat' => new external_format_value('description'),
                            ]
                        ), 'user groups', VALUE_OPTIONAL),
                    'roles' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'roleid'       => new external_value(PARAM_INT, 'role id'),
                                'name'         => new external_value(PARAM_RAW, 'role name'),
                                'shortname'    => new external_value(PARAM_ALPHANUMEXT, 'role shortname'),
                                'sortorder'    => new external_value(PARAM_INT, 'role sortorder')
                            ]
                        ), 'user roles', VALUE_OPTIONAL),
                    'preferences' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'name'  => new external_value(PARAM_RAW, 'The name of the preferences'),
                                'value' => new external_value(PARAM_RAW, 'The value of the custom field'),
                            ]
                    ), 'User preferences', VALUE_OPTIONAL),
                    'enrolledcourses' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'id'  => new external_value(PARAM_INT, 'Id of the course'),
                                'fullname' => new external_value(PARAM_RAW, 'Fullname of the course'),
                                'shortname' => new external_value(PARAM_RAW, 'Shortname of the course')
                            ]
                    ), 'Courses where the user is enrolled - limited by which courses the user is able to see', VALUE_OPTIONAL)
                ]
            )
        );
    }

}

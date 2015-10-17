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
 * Local stuff for cohort enrolment plugin.
 *
 * @package enrol_delayedcohort
 * @copyright 2010 Petr Skoda {@link http://skodak.org}
 * @copyright 2015 Valery Fremaux {@link http://www.mylearningfactory.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/enrol/locallib.php');

/**
 * Event handler for cohort enrolment plugin.
 *
 * We try to keep everything in sync via listening to events,
 * it may fail sometimes, so we always do a full sync in cron too.
 */
class enrol_delayedcohort_handler {
    /**
     * Event processor - cohort member added.
     * @param \core\event\cohort_member_added $event
     * @return bool
     */
    public static function member_added(\core\event\cohort_member_added $event) {
        global $DB, $CFG;

        require_once($CFG->dirroot.'/group/lib.php');

        if (!enrol_is_enabled('delayedcohort')) {
            return true;
        }

        $now = time();

        // Does any enabled cohort instance want to sync with this cohort?
        $sql = "
            SELECT
                e.*,
                r.id as roleexists
            FROM
                {enrol} e
            LEFT JOIN
                {role} r
            ON
                (r.id = e.roleid)
            WHERE
                e.customint3 <= {$now} AND
                e.customint1 = :cohortid AND
                e.enrol = 'delayedcohort'
            ORDER BY
                e.id ASC
        ";
        if (!$instances = $DB->get_records_sql($sql, array('cohortid' => $event->objectid))) {
            return true;
        }

        $plugin = enrol_get_plugin('delayedcohort');

        foreach ($instances as $instance) {
            if ($instance->status != ENROL_INSTANCE_ENABLED ) {
                // No roles for disabled instances.
                $instance->roleid = 0;
            } else if ($instance->roleid and !$instance->roleexists) {
                // Invalid role - let's just enrol, they will have to create new sync and delete this one.
                $instance->roleid = 0;
            }
            unset($instance->roleexists);
            // No problem if already enrolled.
            $plugin->enrol_user($instance, $event->relateduserid, $instance->roleid, 0, 0, ENROL_USER_ACTIVE);

            // Sync groups.
            if ($instance->customint2) {
                if (!groups_is_member($instance->customint2, $event->relateduserid)) {
                    if ($group = $DB->get_record('groups', array('id' => $instance->customint2, 'courseid' => $instance->courseid))) {
                        groups_add_member($group->id, $event->relateduserid, 'enrol_delayedcohort', $instance->id);
                    }
                }
            }
        }

        return true;
    }

    /**
     * Event processor - cohort member removed.
     * @param \core\event\cohort_member_removed $event
     * @return bool
     */
    public static function member_removed(\core\event\cohort_member_removed $event) {
        global $DB;

        $now = time();

        // Does anything want to sync with this cohort?
        if (!$instances = $DB->get_records('enrol', " customint1 = ? AND enrol = ? AND customint3 < ? ", array($event->objectid, 'delayedcohort', $now), 'id ASC')) {
            return true;
        }

        $plugin = enrol_get_plugin('delayedcohort');
        $unenrolaction = $plugin->get_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);

        foreach ($instances as $instance) {
            if (!$ue = $DB->get_record('user_enrolments', array('enrolid' => $instance->id, 'userid' => $event->relateduserid))) {
                continue;
            }
            if ($unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
                $plugin->unenrol_user($instance, $event->relateduserid);

            } else {
                if ($ue->status != ENROL_USER_SUSPENDED) {
                    $plugin->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
                    $context = context_course::instance($instance->courseid);
                    role_unassign_all(array('userid' => $ue->userid, 'contextid' => $context->id, 'component' => 'enrol_delayedcohort', 'itemid' => $instance->id));
                }
            }
        }

        return true;
    }

    /**
     * Event processor - cohort deleted.
     * @param \core\event\cohort_deleted $event
     * @return bool
     */
    public static function deleted(\core\event\cohort_deleted $event) {
        global $DB;

        $now = time();

        // Does anything want to sync with this cohort?
        if (!$instances = $DB->get_records_select('enrol', " customint1 = ? AND enrol = ? AND customint3 < ?", array($event->objectid, 'delayedcohort', $now), 'id ASC')) {
            return true;
        }

        $plugin = enrol_get_plugin('delayedcohort');
        $unenrolaction = $plugin->get_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);

        foreach ($instances as $instance) {
            if ($unenrolaction == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
                $context = context_course::instance($instance->courseid);
                role_unassign_all(array('contextid' => $context->id, 'component' => 'enrol_delayedcohort', 'itemid' => $instance->id));
                $plugin->update_status($instance, ENROL_INSTANCE_DISABLED);
            } else {
                $plugin->delete_instance($instance);
            }
        }

        return true;
    }
}

/**
 * Sync all cohort course links.
 * @param progress_trace $trace
 * @param int $courseid one course, empty mean all
 * @return int 0 means ok, 1 means error, 2 means plugin disabled
 */
function enrol_delayedcohort_sync(progress_trace $trace, $courseid = NULL) {
    global $CFG, $DB, $SITE;

    $config = get_config('enrol_delayedcohort');

    require_once($CFG->dirroot.'/group/lib.php');

    // Purge all roles if cohort sync disabled, those can be recreated later here by cron or CLI.
    if (!enrol_is_enabled('delayedcohort')) {
        $trace->output('Cohort sync plugin is disabled, unassigning all plugin roles and stopping.');
        role_unassign_all(array('component' => 'enrol_delayedcohort'));
        return 2;
    }

    // Unfortunately this may take a long time, this script can be interrupted without problems.
    @set_time_limit(0);
    raise_memory_limit(MEMORY_HUGE);

    $trace->output('Starting user enrolment synchronisation...');

    $allroles = get_all_roles();
    $instances = array(); //cache

    $plugin = enrol_get_plugin('delayedcohort');
    $unenrolaction = $plugin->get_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);

    // Iterate through all not enrolled yet users.
    $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
    $now = time();
    $sql = '
        SELECT
            cm.id,
            cm.userid,
            e.id AS enrolid,
            ue.status,
            '.get_all_user_name_fields(true, 'u').',
            u.email
        FROM
            {cohort_members} cm
        JOIN
            {enrol} e 
        ON
            (e.customint1 = cm.cohortid AND 
            e.enrol = \'delayedcohort\' '.$onecourse.')
        JOIN
            {user} u 
        ON
            (u.id = cm.userid AND u.deleted = 0)
        LEFT JOIN 
            {user_enrolments} ue 
        ON 
            (ue.enrolid = e.id AND 
            ue.userid = cm.userid)
        WHERE 
            (ue.id IS NULL OR
            ue.status = :suspended) AND
            e.customint3 <= '.$now.' AND
            ((e.customint4 = 0) OR (e.customint4 >= '.$now.'))
    ';
    $params = array();
    $params['courseid'] = $courseid;
    $params['suspended'] = ENROL_USER_SUSPENDED;
    $rs = $DB->get_recordset_sql($sql, $params);
    $enrolled = array();
    foreach ($rs as $ue) {
        if (!isset($instances[$ue->enrolid])) {
            $instances[$ue->enrolid] = $DB->get_record('enrol', array('id' => $ue->enrolid));
        }
        $instance = $instances[$ue->enrolid];
        if ($ue->status == ENROL_USER_SUSPENDED) {
            $enrolled[$ue->enrolid][] = $ue;
            $plugin->update_user_enrol($instance, $ue->userid, ENROL_USER_ACTIVE);
            $trace->output("unsuspending: $ue->userid ==> $instance->courseid via delayed cohort $instance->customint1", 1);
        } else {
            $enrolled[$ue->enrolid][] = $ue;
            $plugin->enrol_user($instance, $ue->userid);
            $trace->output("enrolling: $ue->userid ==> $instance->courseid via delayed cohort $instance->customint1", 1);
        }
    }
    $rs->close();

    // Notify if necessary for any enrollements
    if (!empty($config->notifyto) && !empty($enrolled)) {

        foreach ($enrolled as $enrolid => $ues) {
            // for each enrol instance

            // make user list.
            $unames = array();
            $umails = array();
            foreach ($ues as $user) {
                $unames[] = fullname($user);
                $umails[] = $user->email;
            }

            // Send notification
            $instance = $instances[$enrolid];

            $e = new StdClass;
            $e->site = $SITE->shortname;
            $e->cohort = $DB->get_field('cohort', 'name', array('id' => $instance->customint1));
            $e->shortname = $DB->get_field('course', 'shortname', array('id' => $instance->courseid));
            $e->fullname = format_string($DB->get_field('course', 'shortname', array('id' => $instance->courseid)));
            $e->userlist = implode(', ', $unames);
            $e->usermaillist = implode(';', $umails);
            $subject = get_string('notifyaction_mail_object', 'enrol_delayedcohort', $e);
            $notification = get_string('notifyaction_mail_raw', 'enrol_delayedcohort', $e);
            $notification_html = get_string('notifyaction_mail_html', 'enrol_delayedcohort', $e);

            $headers = 'MIME-Version: 1.0'."\r\n".
                'Content-type: text/html; charset=UTF-8'."\r\n".
                'From: '.$CFG->supportemail."\r\n".
                'Reply-To: '.$CFG->noreplyaddress."\r\n".
                'X-Mailer: PHP/' . phpversion();

            mtrace('Sending notification to admins...');
            mail($config->notifyto, $subject, $notification_html, $headers);
        }
    }

    // Run across instances and record event for activated instances
    foreach ($instances as $instance) {
        if ($instance->customint7 == 0) {
            // Fire events to reflect the split..
            $params = array(
                'context' => context_course::instance($instance->courseid),
                'objectid' => $instance->id,
            );
            $event = \enrol_delayedcohort\event\delayedcohort_enrolled::create($params);
            $event->trigger();
        }

        // Mark back in DB we have sent activation event.
        $instance->customint7 = 1;
        $DB->update_record('enrol', $instance);
    }

    // Unenrol as necessary (not anymore in cohort).
    $sql = "
        SELECT
            ue.*,
            e.courseid
        FROM
            {user_enrolments} ue
        JOIN
            {enrol} e
        ON
            (e.id = ue.enrolid AND
            e.enrol = 'delayedcohort' $onecourse) AND
            e.customint3 <= $now
        LEFT JOIN 
            {cohort_members} cm
        ON
            (cm.cohortid = e.customint1 AND
            cm.userid = ue.userid)
        WHERE
            cm.id IS NULL
    ";
    $rs = $DB->get_recordset_sql($sql, array('courseid' => $courseid));
    foreach ($rs as $ue) {
        if (!isset($instances[$ue->enrolid])) {
            $instances[$ue->enrolid] = $DB->get_record('enrol', array('id' => $ue->enrolid));
        }
        $instance = $instances[$ue->enrolid];
        if ($unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
            // Remove enrolment together with group membership, grades, preferences, etc.
            $plugin->unenrol_user($instance, $ue->userid);
            $trace->output("unenrolling: $ue->userid ==> $instance->courseid via delayed cohort $instance->customint1", 1);

        } else { // ENROL_EXT_REMOVED_SUSPENDNOROLES
            // Just disable and ignore any changes.
            if ($ue->status != ENROL_USER_SUSPENDED) {
                $plugin->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
                $context = context_course::instance($instance->courseid);
                role_unassign_all(array('userid' => $ue->userid, 'contextid' => $context->id, 'component' => 'enrol_delayedcohort', 'itemid' => $instance->id));
                $trace->output("suspending and unsassigning all roles: $ue->userid ==> $instance->courseid", 1);
            }
        }
    }
    $rs->close();
    unset($instances);

    // Unenrol enrolled users on modified triggerdate cohorts (not yet enrolled !).
    // Will also unenrol people passing end date if end date unenrol is enabled.
    $sql = "
        SELECT
            ue.*,
            e.courseid
        FROM
            {user_enrolments} ue
        JOIN
            {enrol} e
        ON
            (e.id = ue.enrolid AND
            e.enrol = 'delayedcohort' $onecourse) AND
            (e.customint3 > $now OR 
            (e.customint4 < $now AND e.customchar1 = 1) )
        LEFT JOIN 
            {cohort_members} cm
        ON
            (cm.cohortid = e.customint1 AND
            cm.userid = ue.userid)
    ";
    $rs = $DB->get_recordset_sql($sql, array('courseid' => $courseid));
    foreach ($rs as $ue) {
        if (!isset($instances[$ue->enrolid])) {
            $instances[$ue->enrolid] = $DB->get_record('enrol', array('id' => $ue->enrolid));
        }
        $instance = $instances[$ue->enrolid];
        if ($unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
            // Remove enrolment together with group membership, grades, preferences, etc.
            $plugin->unenrol_user($instance, $ue->userid);
            $trace->output("unenrolling: $ue->userid ==> $instance->courseid via delayed cohort $instance->customint1", 1);

        } else { // ENROL_EXT_REMOVED_SUSPENDNOROLES
            // Just disable and ignore any changes.
            if ($ue->status != ENROL_USER_SUSPENDED) {
                $plugin->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
                $context = context_course::instance($instance->courseid);
                role_unassign_all(array('userid' => $ue->userid, 'contextid' => $context->id, 'component' => 'enrol_delayedcohort', 'itemid' => $instance->id));
                $trace->output("suspending and unsassigning all roles: $ue->userid ==> $instance->courseid", 1);
            }
        }
    }
    $rs->close();
    unset($instances);

    // Now assign all necessary roles to enrolled users - skip suspended instances and users.
    // Skip out of timerange enrolments
    $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
    $now = time();

    $sql = "
        SELECT
            e.roleid,
            ue.userid,
            c.id AS contextid,
            e.id AS itemid,
            e.courseid
        FROM 
            {user_enrolments} ue
        JOIN 
            {enrol} e
        ON
            e.id = ue.enrolid AND
            e.enrol = 'delayedcohort' AND
            e.status = :statusenabled $onecourse AND
            e.customint3 <= {$now} AND
            ((e.customint4 = 0) OR (e.customint4 >= {$now}) OR (e.customchar1 = 0))
        JOIN
            {role} r
        ON
            (r.id = e.roleid)
        JOIN
            {context} c
        ON
            (c.instanceid = e.courseid AND
            c.contextlevel = :coursecontext)
        JOIN
            {user} u
        ON
            (u.id = ue.userid AND
            u.deleted = 0)
        LEFT JOIN
            {role_assignments} ra
        ON
            (ra.contextid = c.id AND
            ra.userid = ue.userid AND
            ra.itemid = e.id AND
            ra.component = 'enrol_delayedcohort'
            AND e.roleid = ra.roleid)
        WHERE
            ue.status = :useractive AND
            ra.id IS NULL
    ";

    $params = array();
    $params['statusenabled'] = ENROL_INSTANCE_ENABLED;
    $params['useractive'] = ENROL_USER_ACTIVE;
    $params['coursecontext'] = CONTEXT_COURSE;
    $params['courseid'] = $courseid;

    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $ra) {
        role_assign($ra->roleid, $ra->userid, $ra->contextid, 'enrol_delayedcohort', $ra->itemid);
        $trace->output("assigning role: $ra->userid ==> $ra->courseid as ".$allroles[$ra->roleid]->shortname, 1);
    }
    $rs->close();

    // Remove unwanted roles - sync role can not be changed, we only remove role when unenrolled.
    // Although still in enrol range.
    $onecourse = $courseid ? "AND e.courseid = :courseid" : "";

    $sql = "
        SELECT
            ra.roleid,
            ra.userid,
            ra.contextid,
            ra.itemid,
            e.courseid
        FROM
            {role_assignments} ra
        JOIN
            {context} c
        ON
            (c.id = ra.contextid AND
            c.contextlevel = :coursecontext)
        JOIN
            {enrol} e
        ON
            (e.id = ra.itemid AND
            e.enrol = 'delayedcohort' $onecourse) AND
            e.customint3 <= {$now} AND
            ((e.customint4 = 0) OR e.customint4 >= {$now} OR (e.customchar1 = 0))
        LEFT JOIN
            {user_enrolments} ue
        ON
            (ue.enrolid = e.id AND
            ue.userid = ra.userid AND ue.status = :useractive)
        WHERE
            ra.component = 'enrol_delayedcohort' AND
            (ue.id IS NULL OR e.status <> :statusenabled)
    ";

    $params = array();
    $params['statusenabled'] = ENROL_INSTANCE_ENABLED;
    $params['useractive'] = ENROL_USER_ACTIVE;
    $params['coursecontext'] = CONTEXT_COURSE;
    $params['courseid'] = $courseid;

    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $ra) {
        role_unassign($ra->roleid, $ra->userid, $ra->contextid, 'enrol_delayedcohort', $ra->itemid);
        $trace->output("unassigning role: $ra->userid ==> $ra->courseid as ".$allroles[$ra->roleid]->shortname, 1);
    }
    $rs->close();

    // Finally sync groups.
    $onecourse = $courseid ? "AND e.courseid = :courseid" : "";

    // Remove invalid.
    $sql = "
        SELECT
            gm.*,
            e.courseid,
            g.name AS groupname
        FROM
            {groups_members} gm
        JOIN
            {groups} g
        ON
            (g.id = gm.groupid)
        JOIN
            {enrol} e
        ON
            (e.enrol = 'delayedcohort' AND
            e.courseid = g.courseid $onecourse) AND
            e.customint3 <= {$now}
        JOIN
            {user_enrolments} ue
        ON
            (ue.userid = gm.userid AND ue.enrolid = e.id)
        WHERE
            gm.component='enrol_delayedcohort' AND
            gm.itemid = e.id AND
            g.id <> e.customint2";
    $params = array();
    $params['courseid'] = $courseid;

    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $gm) {
        groups_remove_member($gm->groupid, $gm->userid);
        $trace->output("removing user from group: $gm->userid ==> $gm->courseid - $gm->groupname", 1);
    }
    $rs->close();

    // Add missing.
    $sql = "
        SELECT
            ue.*,
            g.id AS groupid,
            e.courseid,
            g.name AS groupname
        FROM
            {user_enrolments} ue
        JOIN
            {enrol} e
        ON
            (e.id = ue.enrolid AND
            e.enrol = 'delayedcohort' $onecourse) AND
            e.customint3 <= {$now}
        JOIN
            {groups} g
        ON
            (g.courseid = e.courseid AND
            g.id = e.customint2)
        JOIN
            {user} u
        ON
            (u.id = ue.userid AND
            u.deleted = 0)
        LEFT JOIN
            {groups_members} gm
        ON
            (gm.groupid = g.id AND
            gm.userid = ue.userid)
        WHERE
            gm.id IS NULL
    ";
    $params = array();
    $params['courseid'] = $courseid;

    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $ue) {
        groups_add_member($ue->groupid, $ue->userid, 'enrol_delayedcohort', $ue->enrolid);
        $trace->output("adding user to group: $ue->userid ==> $ue->courseid - $ue->groupname", 1);
    }
    $rs->close();

    $trace->output('...user enrolment synchronisation finished.');

    return 0;
}

/**
 * Enrols all of the users in a cohort through a manual plugin instance.
 *
 * In order for this to succeed the course must contain a valid manual
 * enrolment plugin instance that the user has permission to enrol users through.
 *
 * @global moodle_database $DB
 * @param course_enrolment_manager $manager
 * @param int $cohortid
 * @param int $roleid
 * @return int
 */
function enrol_delayedcohort_enrol_all_users(course_enrolment_manager $manager, $cohortid, $roleid) {
    global $DB;

    $context = $manager->get_context();
    require_capability('moodle/course:enrolconfig', $context);

    $instance = false;
    $instances = $manager->get_enrolment_instances();
    foreach ($instances as $i) {
        if ($i->enrol == 'manual') {
            $instance = $i;
            break;
        }
    }
    $plugin = enrol_get_plugin('manual');
    if (!$instance || !$plugin || !$plugin->allow_enrol($instance) || !has_capability('enrol/'.$plugin->get_name().':enrol', $context)) {
        return false;
    }
    $sql = "
        SELECT
            com.userid
        FROM
            {cohort_members} com
        LEFT JOIN (
            SELECT
                *
            FROM
                {user_enrolments} ue
            WHERE
                ue.enrolid = :enrolid) ue
        ON
            ue.userid = com.userid
        WHERE
            com.cohortid = :cohortid AND
            ue.id IS NULL
    ";
    $params = array('cohortid' => $cohortid, 'enrolid' => $instance->id);
    $rs = $DB->get_recordset_sql($sql, $params);
    $count = 0;
    foreach ($rs as $user) {
        $count++;
        $plugin->enrol_user($instance, $user->userid, $roleid);
    }
    $rs->close();
    return $count;
}

/**
 * Gets all the cohorts the user is able to view.
 *
 * @global moodle_database $DB
 * @param course_enrolment_manager $manager
 * @return array
 */
function enrol_delayedcohort_get_cohorts(course_enrolment_manager $manager) {
    global $DB;

    $context = $manager->get_context();
    $cohorts = array();
    $instances = $manager->get_enrolment_instances();
    $enrolled = array();
    foreach ($instances as $instance) {
        if ($instance->enrol == 'delayedcohort') {
            $enrolled[] = $instance->customint1;
        }
    }
    list($sqlparents, $params) = $DB->get_in_or_equal($context->get_parent_context_ids());
    $sql = "
        SELECT
            id,
            name,
            idnumber,
            contextid
        FROM
            {cohort}
        WHERE
            contextid $sqlparents
        ORDER BY
            name ASC,
            idnumber ASC
    ";
    $rs = $DB->get_recordset_sql($sql, $params);

    foreach ($rs as $c) {
        $context = context::instance_by_id($c->contextid);

        if (!has_capability('moodle/cohort:view', $context)) {
            continue;
        }

        $cohorts[$c->id] = array(
            'cohortid' => $c->id,
            'name' => format_string($c->name, true, array('context' => context::instance_by_id($c->contextid))),
            'users' => $DB->count_records('cohort_members', array('cohortid' => $c->id)),
            'enrolled' => in_array($c->id, $enrolled)
        );
    }
    $rs->close();
    return $cohorts;
}

/**
 * Check if cohort exists and user is allowed to enrol it.
 *
 * @global moodle_database $DB
 * @param int $cohortid Cohort ID
 * @return boolean
 */
function enrol_delayedcohort_can_view_cohort($cohortid) {
    global $DB;
    $cohort = $DB->get_record('cohort', array('id' => $cohortid), 'id, contextid');
    if ($cohort) {
        $context = context::instance_by_id($cohort->contextid);
        if (has_capability('moodle/cohort:view', $context)) {
            return true;
        }
    }
    return false;
}

/**
 * Gets cohorts the user is able to view.
 *
 * @global moodle_database $DB
 * @param course_enrolment_manager $manager
 * @param int $offset limit output from
 * @param int $limit items to output per load
 * @param string $search search string
 * @return array    Array(more => bool, offset => int, cohorts => array)
 */
function enrol_delayedcohort_search_cohorts(course_enrolment_manager $manager, $offset = 0, $limit = 25, $search = '') {
    global $DB;

    $context = $manager->get_context();
    $cohorts = array();
    $instances = $manager->get_enrolment_instances();
    $enrolled = array();
    foreach ($instances as $instance) {
        if ($instance->enrol == 'delayedcohort') {
            $enrolled[] = $instance->customint1;
        }
    }

    list($sqlparents, $params) = $DB->get_in_or_equal($context->get_parent_context_ids());

    // Add some additional sensible conditions.
    $tests = array('contextid ' . $sqlparents);

    // Modify the query to perform the search if required.
    if (!empty($search)) {
        $conditions = array(
            'name',
            'idnumber',
            'description'
        );
        $searchparam = '%' . $DB->sql_like_escape($search) . '%';
        foreach ($conditions as $key => $condition) {
            $conditions[$key] = $DB->sql_like($condition, "?", false);
            $params[] = $searchparam;
        }
        $tests[] = '(' . implode(' OR ', $conditions) . ')';
    }
    $wherecondition = implode(' AND ', $tests);

    $sql = "
        SELECT
            id,
            name,
            idnumber,
            contextid,
            description
        FROM
            {cohort}
        WHERE
            $wherecondition
        ORDER BY
            name ASC,
            idnumber ASC
    ";
    $rs = $DB->get_recordset_sql($sql, $params, $offset);

    // Produce the output respecting parameters.
    foreach ($rs as $c) {
        // Track offset.
        $offset++;
        // Check capabilities.
        $context = context::instance_by_id($c->contextid);
        if (!has_capability('moodle/delayedcohort:view', $context)) {
            continue;
        }
        if ($limit === 0) {
            // We have reached the required number of items and know that there are more, exit now.
            $offset--;
            break;
        }
        $cohorts[$c->id] = array(
            'cohortid' => $c->id,
            'name'     => shorten_text(format_string($c->name, true, array('context' => context::instance_by_id($c->contextid))), 35),
            'users'    => $DB->count_records('cohort_members', array('cohortid' => $c->id)),
            'enrolled' => in_array($c->id, $enrolled)
        );
        // Count items.
        $limit--;
    }
    $rs->close();
    return array('more' => !(bool)$limit, 'offset' => $offset, 'cohorts' => $cohorts);
}

function enrol_delayedcohort_get_planned_enrols(&$plannecourses, $lightweighted = false) {
    global $DB;

    if ($lightweighted) {
        $fields = '
            e.id,
            e.courseid
        ';
    } else {
        $fields = '
            e.id,
            c.id as courseid,
            c.shortname,
            c.fullname,
            c.idnumber,
            ch.name as chname,
            ch.idnumber as chidnumber,
            e.roleid,
            e.customint1,
            e.customint2,
            e.customint3,
            e.customint4
       ';
    }

    $sql = "
        SELECT
            {$fields}
        FROM
            {enrol} e,
            {course} c,
            {cohort} ch
        WHERE
            ch.id = e.customint1 AND
            e.courseid = c.id AND
            enrol = ?
        ORDER BY
            customint3 DESC
    ";

    if ($enrols = $DB->get_records_sql($sql, array('delayedcohort'))) {
        foreach ($enrols as $e) {
            $plannedcourses[] = $e->courseid;
        }
    }

    return $enrols;
}
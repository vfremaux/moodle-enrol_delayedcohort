<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/enrol/delayedcohort/locallib.php');

/**
 * Get legaldates of the user in a course, if an active delayedcohort
 * is available for this user.
 * @param int $courseid
 * @param int $userid
 * @param array $enrol
 */
function enrol_delayedcohort_get_legaldates($courseid, $userid, $enrol = null) {
    global $DB;

    $params = [
        'courseid' => $courseid,
        'userid' => $userid
    ];

    $params = ['courseid' => $courseid, 'userid' => $userid];

    // Focussing by idnumber or by id, should be more precise and reduce to one
    // The possible candidates.
    $enrolidclause = '';
    if (!is_null($enrol)) {
        list ($enrolidfield, $enrolid) = $enrol;
        if (!in_array($enrolidfield, ['id', 'name'])) {
            throw new moodle_exception("Bad enrol instance identification field. 'id' or 'name' expected. ");
        }
        $enrolidclause = "
            e.{$enrolidfield} = :idnumber AND
        ";
        $params['idnumber'] = $enrolid;
    }

    $sql = "
        SELECT
            ue.id as ueid,
            ue.status,
            e.roleid,
            e.enrolstartdate,
            e.enrolenddate,
            e.customint3 as legalstartdate,
            e.customint4 as legalenddate
        FROM
            {enrol} e,
            {user_enrolments} ue
        WHERE
            e.id = ue.enrolid AND
            e.enrol = 'delayedcohort' AND
            e.status = 0 AND
            ue.status = 0 AND
            $enrolidclause
            ue.userid = :userid AND
            e.courseid = :courseid
        ORDER BY
            e.customint5
    ";
    $ues = $DB->get_records_sql($sql, $params);

    // deal with the case were many instances overlap. This should not actually
    // be the case in real life, but nothing will forbid it...

    $legaldates = null;
    if (!empty($ues)) {

        if ((is_null($enrol) || ($enrolidfield == 'name')) && count($ues) > 1) {
            if (is_null($enrol)) {
                throw new moodle_exception("There is more than one instance of active delayedcohort for the user in the course. 
                This is not an allowed situation");
            } else {
                throw new moodle_exception("Two instances got by name have same name This is not an allowed situation");
            }
        }

        $legaldates = [];
        $legaldates['startdate'] = 0;
        $legaldates['enddate'] = 0;
        foreach ($ues as $ue) {
            // First resolution hypothesis : take the last upcomming by enrolstartdate.
            $legaldates['startdate'] = $ue->legalstartdate ? $ue->legalstartdate : $ue->enrolstartdate;
            $legaldates['enddate'] = $ue->legalenddate ? $ue->legalenddate : $ue->enrolenddate;
        }
    }

    return $legaldates;
}
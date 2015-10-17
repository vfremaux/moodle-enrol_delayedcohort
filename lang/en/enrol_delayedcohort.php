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
 * Strings for component 'enrol_cohort', language 'en'.
 *
 * @package    enrol_cohort
 * @copyright  2010 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['delayedcohort:plan'] = 'Schedule cohorts on global planner';
$string['delayedcohort:config'] = 'Configure cohort instances';
$string['delayedcohort:unenrol'] = 'Unenrol suspended users';

$string['addenrol'] = 'Add new enrolment';
$string['addgroup'] = 'Add to group';
$string['ajaxmore'] = 'More...';
$string['all'] = 'All courses';
$string['assignrole'] = 'Assign role';
$string['bycohort'] = 'By cohort';
$string['bycourse'] = 'By course';
$string['cohortsearch'] = 'Search';
$string['cohortsplanner'] = 'Cohorts planning';
$string['delayed'] = 'delayed';
$string['delayedcohortsplanner'] = 'Delayed Cohorts Planner';
$string['enddate'] = 'End date';
$string['endsat'] = 'Ends at {$a}';
$string['endsatcontinue'] = 'Ends at {$a} without unenrol';
$string['event_delayedcohort_created'] = 'Delayed cohort instance created ';
$string['event_delayedcohort_deleted'] = 'Delayed cohort instance deleted ';
$string['event_delayedcohort_enrolled'] = 'Delayed cohort enrolled ';
$string['event_delayedcohort_ended'] = 'Delayed cohort ended ';
$string['instanceexists'] = 'Cohort is already synchronised with selected role';
$string['noprogrammablecohorts'] = 'No programmable cohorts';
$string['plannedcourses'] = 'Planned courses';
$string['planner'] = 'Cohorts activation planner';
$string['pluginname'] = 'Delayed Cohort sync';
$string['pluginname_desc'] = 'Cohort enrolment plugin synchronises cohort members with course participants.';
$string['status'] = 'Active';
$string['triggerdate'] = 'Sync Trigger date';
$string['unassigned'] = 'Unassigned';
$string['unplannedcourses'] = 'Unplanned courses';
$string['unenrolonend'] = 'Unenrol when passed end';
$string['notifyto'] = 'Notify to';
$string['notifyto_help'] = 'If not blanck, will send a notification to provided emails';
$string['notenabled'] = 'The delayed cohort enrolment plugin is not enabled in central configuration.';

$string['notifyaction_mail_object'] = '{$a->site}: Delayed cohort {$a->cohort} activation in {$a->shortname}';
$string['notifyaction_mail_raw'] = '
Delayed Cohort Enrolment
------------------------
Following users from cohort {$a->cohort} have been enrolled into course [{$a->shortname}] {$a->fullname}:

{$a->userlist}

{$a->usermaillist}
';

$string['notifyaction_mail_html'] = '
<h2>Delayed Cohort Enrolment</h2>
<p>Following users from cohort <b>{$a->cohort}</b> have been enrolled into course <b>[{$a->shortname}] {$a->fullname}</b>:</p>

<p>{$a->userlist}</p>

<p>{$a->usermaillist}</p>
';

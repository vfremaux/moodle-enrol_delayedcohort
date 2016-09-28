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

namespace enrol_delayedcohort\task;

defined('MOODLE_INTERNAL') || die();

/**
 * A scheduled task for forum cron.
 *
 * @todo MDL-44734 This job will be split up properly.
 *
 * @package   enrol_delayedcohort
 * @category  enrol
 * @author    Valery Fremaux <valery.fremaux@gmail.com>
 * @copyright 2015 Valery Fremaux {@link http://www.mylearningfactory.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/lib/weblib.php');

class enrolsync_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('enrolsync_task', 'enrol_delayedcohort');
    }

    /**
     * Run trainingsessions cron.
     */
    public function execute() {
        global $CFG;

        $trace = new \text_progress_trace();
        require_once($CFG->dirroot.'/enrol/delayedcohort/locallib.php');
        enrol_delayedcohort_sync($trace);
    }
}

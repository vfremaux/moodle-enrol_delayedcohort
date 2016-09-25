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
 * This file contains an event for when a delayed cohort enrol instance is deleted.
 *
 * @package   enrol_delayedcohort
 * @category  enrol
 * @author    Valery Fremaux <valery.fremaux@gmail.com>
 * @copyright 2015 Valery Fremaux {@link http://www.mylearningfactory.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_delayedcohort\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event fired when a delayedcohort is created.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      @type int anonymous if certificate is anonymous.
 *      @type int cmid course module id.
 * }
 */
class delayedcohort_deleted extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'enrol';
    }

    public static function get_name() {
        return get_string('event_delayedcohort_deleted', 'enrol_delayedcohort');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The programmmed delayed cohort enrol '$this->objectid' has been created for course {$this->other->courseid}. ";
    }

    /**
     * Replace add_to_log() statement.Do this only for the case when anonymous mode is off,
     * since this is what was happening before.
     *
     * @return array of parameters to be passed to legacy add_to_log() function.
     */
    protected function get_legacy_logdata() {
        if ($this->anonymous) {
            return null;
        } else {
            return parent::get_legacy_logdata();
        }
    }

    /**
     * Custom validations.
     *
     * @throws \coding_exception in case of any problems.
     */
    protected function validate_data() {

        // Call parent validations.
        parent::validate_data();
    }
}


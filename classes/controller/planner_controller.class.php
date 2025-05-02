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

namespace enrol_delayedcohort\controller;

defined('MOODLE_INTERNAL') || die();

/**
 * @package   enrol_delayedcohort
 * @category  enrol
 * @author    Valery Fremaux <valery.fremaux@gmail.com>
 * @copyright 2015 Valery Fremaux {@link http://www.mylearningfactory.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class planner_controller {

    protected $cmd;

    protected $received;

    public function receive($cmd, $data = null) {

        $this->cmd = $cmd;

        if (!is_null($data)) {
            $this->data = $data;
            $this->received = true;
        }

        switch ($cmd) {
            case 'delete': {
                $this->data->id = required_param('id', PARAM_INT);
            }
        }
    }

    public function process() {

        if (!$this->received) {
            throw new coding_exception("Controller must receive data to be processed");
        }

        if ($this->cmd == 'delete') {
            require_sesskey();
            $plugins   = enrol_get_plugins(false);
            $instance = $DB->get_record('enrol', ['id' => $this->data->id]);
            $plugin = $plugins['delayedcohort'];
            $plugin->delete_instance($instance);

            $params = [
                'context' => context_course::instance($instance->courseid),
                'objectid' => $this->data->id,
                'other' => [
                    'courseid' => $instance->courseid,
                ],
            ];
            $event = \enrol_delayedcohort\event\delayedcohort_deleted::create($params);
            $event->trigger();
        }
    }
}

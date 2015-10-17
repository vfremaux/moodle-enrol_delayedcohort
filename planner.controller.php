<?php

if (!defined('MOODLE_INTERNAL')) die('You cannot use this script this way');

if ($action == 'delete') {
    $id = required_param('id', PARAM_INT);
    $plugins   = enrol_get_plugins(false);
    $instance = $DB->get_record('enrol', array('id' => $id));
    $plugin = $plugins['delayedcohort'];
    $plugin->delete_instance($instance);

    $params = array(
        'context' => context_course::instance($instance->courseid),
        'objectid' => $id,
        'other' => array(
            'courseid' => $instance->courseid,
        ),
    );
    $event = \enrol_delayedcohort\event\delayedcohort_deleted::create($params);
    $event->trigger();
}
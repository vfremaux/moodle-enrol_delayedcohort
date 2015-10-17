<?php

class enrol_delayedcohort_renderer extends plugin_renderer_base {

    function tabs() {
        global $OUTPUT;

        $currenttab = optional_param('view', 'bycourse', PARAM_TEXT);

        $row[] = new tabobject('bycourse',
                               new moodle_url('/enrol/delayedcohort/planner.php', array('view' => 'bycourse')),
                               get_string('bycourse', 'enrol_delayedcohort'));

        $row[] = new tabobject('bycohort',
                               new moodle_url('/enrol/delayedcohort/planner.php', array('view' => 'bycohort')),
                               get_string('bycohort', 'enrol_delayedcohort'));

        $row[] = new tabobject('unassigned',
                               new moodle_url('/enrol/delayedcohort/planner.php', array('view' => 'unassigned')),
                               get_string('unassigned', 'enrol_delayedcohort'));

        $str = '<div class="enrol-delayedcohort-tabs">';
        $str .= $OUTPUT->tabtree($row, $currenttab);
        $str .= '</div>';

        return $str;
    }
    
    function category_selector($url) {
        global $OUTPUT;

        $str = '';
        $choice = optional_param('category', 0, PARAM_INT);
        $categories = coursecat::make_categories_list();
        $str .= $OUTPUT->single_select($url, 'category', $categories, $choice, array('' => get_string('all', 'enrol_delayedcohort')));

        return $str;
    }
}
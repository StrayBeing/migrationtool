<?php
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->libdir.'/formslib.php');

class export_form extends moodleform {

    public function definition() {

        $mform = $this->_form;

        $courses = get_courses();
        $list = [];
        foreach ($courses as $course) {
            $list[$course->id] = $course->fullname;
        }

        $mform->addElement('select',
            'courseid',
            'Course to export',
            $list
        );

        $mform->addElement('select',
            'mode',
            'Export mode',
            [
                'structure' => 'Structure only',
                'full' => 'Full backup'
            ]
        );

        $this->add_action_buttons(true, 'Export course');
    }
}

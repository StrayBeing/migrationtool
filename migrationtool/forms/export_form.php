<?php
//appearance change later
namespace local_migrationtool\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class export_form extends \moodleform {
    public function definition(): void {
        global $DB;

        $mform = $this->_form;
        $courses = $DB->get_records_select('course', 'id <> :siteid', ['siteid' => SITEID], 'fullname ASC', 'id, fullname, shortname');
        $options = [];
        foreach ($courses as $course) {
            $options[$course->id] = format_string($course->fullname) . ' [' . s($course->shortname) . ']';
        }

        $mform->addElement('autocomplete', 'courses', get_string('selectcourses', 'local_migrationtool'), $options, [
            'multiple' => true,
        ]);
        $mform->addRule('courses', null, 'required', null, 'client');
        $mform->setType('courses', PARAM_INT);

        $this->add_action_buttons(true, get_string('exportsubmit', 'local_migrationtool'));
    }
}

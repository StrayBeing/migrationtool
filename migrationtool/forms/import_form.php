<?php
//import form, appearance change later
namespace local_migrationtool\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class import_form extends \moodleform {
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('filepicker', 'migrationzip', get_string('migrationzip', 'local_migrationtool'), null, [
            'accepted_types' => ['.zip'],
            'maxbytes' => 0,
        ]);
        $mform->addRule('migrationzip', null, 'required', null, 'client');
        $this->add_action_buttons(true, get_string('analysesubmit', 'local_migrationtool'));
    }
}

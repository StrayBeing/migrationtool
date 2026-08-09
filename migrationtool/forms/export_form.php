<?php
//appearance change later
namespace local_migrationtool\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class export_form extends \moodleform {
    public function definition(): void {
        global $DB;

        $mform = $this->_form;
        $courses = $DB->get_records_select('course', 'id <> :siteid', ['siteid' => SITEID], 'fullname ASC',
            'id, fullname, shortname');
        $options = [];
        foreach ($courses as $course) {
            $options[$course->id] = format_string($course->fullname) . ' [' . s($course->shortname) . ']';
        }

        $mform->addElement('autocomplete', 'courses', get_string('selectcourses', 'local_migrationtool'), $options, [
            'multiple' => true,
        ]);
        $mform->addRule('courses', null, 'required', null, 'client');
        $mform->setType('courses', PARAM_INT);

        $mform->addElement('header', 'scopeheader', get_string('scopeheader', 'local_migrationtool'));
        $mform->setExpanded('scopeheader', true);

        $mform->addElement('static', 'scopestructure', get_string('scopestructure', 'local_migrationtool'),
            get_string('scopestructuredesc', 'local_migrationtool'));

        $mform->addElement('advcheckbox', 'scopeactivities', get_string('scopeactivities', 'local_migrationtool'),
            get_string('scopeactivitiesdesc', 'local_migrationtool'), null, [0, 1]);
        $mform->setDefault('scopeactivities', 1);
        $mform->setType('scopeactivities', PARAM_BOOL);

        $mform->addElement('advcheckbox', 'scopefiles', get_string('scopefiles', 'local_migrationtool'),
            get_string('scopefilesdesc', 'local_migrationtool'), null, [0, 1]);
        $mform->setDefault('scopefiles', 1);
        $mform->setType('scopefiles', PARAM_BOOL);

        $mform->addElement('advcheckbox', 'scopequestionbank', get_string('scopequestionbank', 'local_migrationtool'),
            get_string('scopequestionbankdesc', 'local_migrationtool'), null, [0, 1]);
        $mform->setDefault('scopequestionbank', 1);
        $mform->setType('scopequestionbank', PARAM_BOOL);

        $mform->addElement('advcheckbox', 'scopeblocks', get_string('scopeblocks', 'local_migrationtool'),
            get_string('scopeblocksdesc', 'local_migrationtool'), null, [0, 1]);
        $mform->setDefault('scopeblocks', 1);
        $mform->setType('scopeblocks', PARAM_BOOL);

        $mform->addElement('static', 'scopeexcluded', get_string('scopeexcluded', 'local_migrationtool'),
            get_string('scopeexcludeddesc', 'local_migrationtool'));

        $this->add_action_buttons(true, get_string('exportsubmit', 'local_migrationtool'));
    }
}

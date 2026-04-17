class restore_form extends moodleform {

    public function definition() {

        $mform = $this->_form;

        $mform->addElement('filepicker',
            'backupfile',
            'Backup (.mbz)',
            null,
            ['accepted_types'=>'.mbz']
        );

        $mform->addElement('select',
            'categoryid',
            'Target category',
            core_course_category::make_categories_list()
        );

        $mform->addElement('select',
            'mode',
            'Migration mode',
            [
                'structure'=>'Structure only',
                'full'=>'Full migration'
            ]
        );

        $this->add_action_buttons(true,'Start migration');
    }
}

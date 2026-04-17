<?php

require('../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/local/migrationtool/export.php');
$PAGE->set_title('Export course');
$PAGE->set_heading('Export course');

require_once('forms/export_form.php');

$mform = new export_form();

echo $OUTPUT->header();

if ($data = $mform->get_data()) {

    require_once('classes/backup_manager.php');

    $manager = new \local_migrationtool\backup_manager();

    $file = $manager->export_course($data->courseid, $data->mode);

    echo "Backup created: ".$file;
}

$mform->display();

echo $OUTPUT->footer();

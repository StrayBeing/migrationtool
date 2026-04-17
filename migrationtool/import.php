<?php

require('../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/local/migrationtool/import.php');
$PAGE->set_title('Import course');
$PAGE->set_heading('Import course');

echo $OUTPUT->header();

$dir = $CFG->dataroot.'/migrationtool/backups/';

$files = glob($dir.'*.mbz');

echo "<h3>Select backup</h3>";

foreach ($files as $file) {

    $name = basename($file);

    $url = new moodle_url('/local/migrationtool/run_import.php', [
        'file' => $name
    ]);

    echo html_writer::link($url, $name);
    echo "<br>";
}

echo $OUTPUT->footer();

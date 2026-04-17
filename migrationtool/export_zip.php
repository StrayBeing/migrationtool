<?php
require('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

global $DB, $OUTPUT, $PAGE, $CFG;

require_once('classes/backup_manager.php');

$PAGE->set_url('/local/migrationtool/export_zip.php');
$PAGE->set_title('Export ZIP');
$PAGE->set_heading('Export courses to ZIP');

echo $OUTPUT->header();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $courses = required_param_array('courses', PARAM_INT);

    $manager = new \local_migrationtool\backup_manager();

    // Katalog tymczasowy dla mbz
    $tmpdir = $CFG->dataroot.'/migrationtool/tmp/'.uniqid();
    if (!is_dir($tmpdir)) {
        mkdir($tmpdir, 0775, true);
    }

    $mbzfiles = [];
    foreach ($courses as $cid) {
        $mbzfiles[] = $manager->export_course($cid, $tmpdir);
    }

    // Tworzenie ZIP
    $zipfile = $CFG->dataroot.'/migrationtool/backups/courses_export_'.time().'.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipfile, ZipArchive::CREATE) !== true) {
        echo $OUTPUT->notification("Nie udało się utworzyć ZIP", 'notifyerror');
        echo $OUTPUT->footer();
        exit;
    }

    foreach ($mbzfiles as $file) {
        $zip->addFile($file, basename($file));
    }

    $zip->close();

    echo $OUTPUT->notification("ZIP utworzony: ".basename($zipfile), 'notifysuccess');

    $url = new moodle_url('/local/migrationtool/download.php', ['file' => basename($zipfile)]);
    echo html_writer::link($url, 'Pobierz ZIP');

} else {
    $courses = $DB->get_records('course');

    echo "<form method='post'>";
    echo "<h3>Wybierz kursy do eksportu</h3>";
    echo "<select name='courses[]' multiple size='10'>";
    foreach ($courses as $course) {
        if ($course->id == 1) continue; // pomijamy frontpage
        echo "<option value='{$course->id}'>{$course->fullname}</option>";
    }
    echo "</select><br><br>";
    echo "<input type='submit' value='Export ZIP'>";
    echo "</form>";
}

echo $OUTPUT->footer();

<?php
require('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

global $CFG, $DB;

$backupdir = $CFG->dataroot.'/migrationtool/backups';
$backups = glob($backupdir.'/*.mbz');

if (optional_param('submit', false, PARAM_BOOL)) {
    $filename = required_param('file', PARAM_TEXT);
    $categoryid = required_param('categoryid', PARAM_INT);

    $filepath = $backupdir.'/'.$filename;

    if (!file_exists($filepath)) {
        echo $OUTPUT->notification("Nie znaleziono backupu: $filepath", 'notifyproblem');
    } else {
        $cli = $CFG->dirroot.'/admin/cli/restore_backup.php';

        // wywołanie CLI bez sudo
        $command = "php {$cli} --file={$filepath} --categoryid={$categoryid} 2>&1";
        echo html_writer::tag('pre', "Uruchamiam: $command");

        exec($command, $output, $return_var);

        // pokażemy output w przeglądarce
        echo html_writer::tag('pre', implode("\n", $output));

        if ($return_var !== 0) {
            echo $OUTPUT->notification("Błąd przy przywracaniu. Kod powrotu: $return_var", 'notifyproblem');
        } else {
            echo $OUTPUT->notification("Backup $filename został przywrócony do kategorii ID $categoryid.", 'notifysuccess');
        }
    }
}

// formularz
echo $OUTPUT->header();
echo html_writer::tag('h2', 'Import kursu z backupu');

echo html_writer::start_tag('form', ['method'=>'post']);
echo html_writer::tag('label', 'Wybierz backup:');
echo html_writer::start_tag('select', ['name'=>'file']);
foreach ($backups as $file) {
    $name = basename($file);
    echo html_writer::tag('option', $name, ['value'=>$name]);
}
echo html_writer::end_tag('select');
echo html_writer::empty_tag('br');

echo html_writer::tag('label', 'Wybierz kategorię docelową:');
echo html_writer::start_tag('select', ['name'=>'categoryid']);
$categories = $DB->get_records('course_categories');
foreach ($categories as $cat) {
    echo html_writer::tag('option', $cat->name, ['value'=>$cat->id]);
}
echo html_writer::end_tag('select');
echo html_writer::empty_tag('br');

echo html_writer::empty_tag('input', ['type'=>'submit','name'=>'submit','value'=>'Importuj']);
echo html_writer::end_tag('form');
echo $OUTPUT->footer();

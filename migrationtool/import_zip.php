<?php
require('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

use moodle_url;

$PAGE->set_url('/local/migrationtool/import_zip.php');
$PAGE->set_title('Import ZIP kursów');
$PAGE->set_heading('Import ZIP kursów');

echo $OUTPUT->header();

// Katalog tymczasowy do rozpakowania ZIP
$tmpbase = $CFG->dataroot.'/migrationtool/tmp/';
if (!is_dir($tmpbase)) {
    mkdir($tmpbase, 0775, true);
}

// Domyślna mapa kurs → kategoria (plik TSV: COURSEID TAB CATEGORYID)
$mapfile = $CFG->dataroot.'/migrationtool/course_category_map.txt';
$map = [];
if (file_exists($mapfile)) {
    $lines = file($mapfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        list($cid, $catid) = explode("\t", $line);
        $map[$cid] = $catid;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['zipfile']['tmp_name'])) {

    $uploadedfile = $_FILES['zipfile'];
    if ($uploadedfile['error'] !== UPLOAD_ERR_OK) {
        echo $OUTPUT->notification('Błąd przy przesyłaniu pliku ZIP', 'notifyproblem');
    } else {
       $tmpzip = $tmpbase.uniqid().'.zip';
        move_uploaded_file($uploadedfile['tmp_name'], $tmpzip);

        $zip = new ZipArchive();
        if ($zip->open($tmpzip) === TRUE) {
            $tmpdir = $tmpbase.uniqid();
            mkdir($tmpdir, 0775, true);
            $zip->extractTo($tmpdir);
            $zip->close();

            // Rekurencyjne znalezienie wszystkich .mbz
            $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpdir));
            foreach ($rii as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'mbz') continue;

                $mbz = $file->getPathname();
                // Pobranie courseid z nazwy pliku lub z mapy
                preg_match('/-course-(\d+)-/', basename($mbz), $matches);
                $courseid = $matches[1] ?? 0;
                $categoryid = $map[$courseid] ?? 1; // domyślnie 1

                echo html_writer::tag('h4', "Przywracanie kursu ID {$courseid} do kategorii ID {$categoryid}");

		$command = PHP_BINDIR . "/php " . escapeshellarg($CFG->dirroot.'/admin/cli/restore_backup.php') .
           " --file=" . escapeshellarg($mbz) .
           " --categoryid=" . escapeshellarg($categoryid) .
           " 2>&1";
                echo html_writer::tag('pre', "Uruchamiam: $command");
                exec($command, $output, $return_var);
                echo html_writer::tag('pre', implode("\n", $output));

                if ($return_var !== 0) {
                    echo $OUTPUT->notification("Błąd przy restore kursu: ".basename($mbz), 'notifyproblem');
                } else {
                    echo $OUTPUT->notification("Restore zakończony: ".basename($mbz), 'notifysuccess');
                }
            }

        } else {
            echo $OUTPUT->notification('Nie można otworzyć pliku ZIP', 'notifyproblem');
        }
    }
}

// Formularz uploadu
echo html_writer::start_tag('form', ['method'=>'post', 'enctype'=>'multipart/form-data']);
echo html_writer::tag('label', 'Wybierz plik ZIP z backupami:');
echo html_writer::empty_tag('input', ['type'=>'file', 'name'=>'zipfile', 'required'=>true]);
echo html_writer::empty_tag('br');
echo html_writer::empty_tag('input', ['type'=>'submit', 'value'=>'Importuj']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();

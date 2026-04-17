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

    $tmpdir = $CFG->dataroot.'/migrationtool/tmp/'.uniqid();
    mkdir($tmpdir,0775,true);

    $mbzfiles = [];
    $map = [];
    $categories = [];

    foreach ($courses as $cid) {

        $course = $DB->get_record('course',['id'=>$cid],'*',MUST_EXIST);

        $map[$cid] = $course->category;

        $mbzfiles[] = $manager->export_course($cid,$tmpdir);

        $cat = $DB->get_record('course_categories',['id'=>$course->category]);

        while ($cat) {
            $categories[$cat->id] = $cat;

            if ($cat->parent == 0) break;

            $cat = $DB->get_record('course_categories',['id'=>$cat->parent]);
        }
    }

    // zapis kategorii
    $catsfile = $tmpdir.'/moodle_categories.json';
    file_put_contents($catsfile,json_encode($categories));

    // zapis mapy kursów
    $mapfile = $tmpdir.'/course_category_map.txt';

    $maptext="";
    foreach($map as $cid=>$catid){
        $maptext.=$cid."\t".$catid."\n";
    }

    file_put_contents($mapfile,$maptext);

    // tworzenie ZIP
    $zipfile = $CFG->dataroot.'/migrationtool/backups/courses_export_'.time().'.zip';

    $zip = new ZipArchive();
    $zip->open($zipfile,ZipArchive::CREATE);

    foreach($mbzfiles as $file){
        $zip->addFile($file,"courses/".basename($file));
    }

    $zip->addFile($catsfile,"moodle_categories.json");
    $zip->addFile($mapfile,"course_category_map.txt");

    $zip->close();

    echo $OUTPUT->notification("ZIP utworzony: ".basename($zipfile),'notifysuccess');

    $url = new moodle_url('/local/migrationtool/download.php',
        ['file'=>basename($zipfile)]
    );

    echo html_writer::link($url,'Pobierz ZIP');

} else {

    $courses = $DB->get_records('course');

    echo "<form method='post'>";
    echo "<h3>Wybierz kursy</h3>";

    echo "<select name='courses[]' multiple size='15'>";

    foreach($courses as $course){

        if($course->id==1) continue;

        echo "<option value='{$course->id}'>{$course->fullname}</option>";
    }

    echo "</select><br><br>";

    echo "<input type='submit' value='Export ZIP'>";
    echo "</form>";
}

echo $OUTPUT->footer();

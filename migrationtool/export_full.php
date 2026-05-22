<?php
require('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());
require_once(__DIR__.'/classes/service/plugin_export_service.php');
require_once(__DIR__.'/classes/service/export_metadata_service.php');
global $DB, $CFG, $OUTPUT, $PAGE;

require_once('classes/backup_manager.php');

$PAGE->set_url('/local/migrationtool/export_full.php');
$PAGE->set_title('Full migration export');
$PAGE->set_heading('Eksport całej platformy kursów');

echo $OUTPUT->header();

$manager = new \local_migrationtool\backup_manager();

$tmpdir = $CFG->dataroot.'/migrationtool/exports/export_'.time();
mkdir($tmpdir,0775,true);
mkdir($tmpdir.'/courses',0775,true);

$courses = $DB->get_records_sql("
SELECT id,category
FROM {course}
WHERE id > 1
");

$map=[];
$categories=[];
$total=count($courses);
$current=0;

echo "<h3>Eksport kursów</h3>";
echo "<progress id='progress' value='0' max='100' style='width:100%'></progress>";
echo "<div id='log'></div>";

foreach($courses as $course){

    $current++;

    echo "<script>
    document.getElementById('log').innerHTML += 'Backup course {$course->id}<br>';
    document.getElementById('progress').value=".intval(($current/$total)*100).";
    </script>";

    flush();
    ob_flush();

    $mbz = $manager->export_course($course->id,$tmpdir.'/courses');

    $map[$course->id]=$course->category;

    $cat = $DB->get_record('course_categories',['id'=>$course->category]);

    while($cat){

        $categories[$cat->id]=$cat;

        if($cat->parent==0) break;

        $cat=$DB->get_record('course_categories',['id'=>$cat->parent]);
    }
}

$metaservice =
    new \local_migrationtool\service\export_metadata_service();

$metaservice->save_categories(
    $tmpdir.'/moodle_categories.json',
    $categories
);

$metaservice->save_course_map(
    $tmpdir.'/course_category_map.txt',
    $map
);

$pluginservice =
    new \local_migrationtool\service\plugin_export_service();

$pluginservice->export_plugins(
    $tmpdir.'/plugins.json'
);
$metaservice->save_migration_info(
    $tmpdir.'/migration_info.json',
    $total
);
echo $OUTPUT->notification("Export finished",'notifysuccess');

echo "<p>Folder exportu:</p>";
echo "<pre>".$tmpdir."</pre>";

echo "<p>Skopiuj folder na drugi serwer np:</p>";

echo "<pre>
rsync -av $tmpdir user@server:/moodledata/migration/
</pre>";

echo $OUTPUT->footer();

<?php
require('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());
require_once(__DIR__.'/classes/service/category_service.php');
require_once(__DIR__.'/classes/service/plugin_service.php');
global $CFG,$DB,$OUTPUT,$PAGE;

$PAGE->set_url('/local/migrationtool/import_folder.php');
$PAGE->set_title('Import folder');
$PAGE->set_heading('Import migracji z folderu');

echo $OUTPUT->header();

if($_SERVER['REQUEST_METHOD']==='POST'){

$folder = required_param('folder', PARAM_RAW);
$confirm = optional_param('confirm',0,PARAM_INT);

$pluginfile = $folder.'/plugins.json';

$pluginservice =
    new \local_migrationtool\service\plugin_service();

[$missing_mod, $missing_qtype] =
    $pluginservice->check_plugins($pluginfile);

/* ---------- WARNINGS ---------- */

if((!empty($missing_mod) || !empty($missing_qtype)) && !$confirm){

echo "<div style='border:2px solid #e74c3c;padding:15px;margin-bottom:20px;background:#fff5f5'>";
echo "<h3>⚠ Plugin compatibility warning</h3>";

if(!empty($missing_mod)){

echo "<b>Missing activity modules:</b><br>";

foreach($missing_mod as $m){
echo "mod_{$m}<br>";
}

echo "<br>";
}

if(!empty($missing_qtype)){

echo "<b>Missing question types:</b><br>";

foreach($missing_qtype as $q){
echo "qtype_{$q}<br>";
}

}

echo "<br>Import może spowodować uszkodzenie aktywności lub quizów.";

echo "<br><br>";

echo "<form method='post'>";

echo "<input type='hidden' name='folder' value='".htmlspecialchars($folder)."'>";
echo "<input type='hidden' name='confirm' value='1'>";

echo "<button style='padding:10px;background:#e74c3c;color:white;border:none'>
Continue import anyway
</button>";

echo "</form>";

echo "<br>";

echo "<a href='import_folder.php'>Cancel</a>";

echo "</div>";

echo $OUTPUT->footer();
exit;

}
$catsfile = $folder.'/moodle_categories.json';
$mapfile = $folder.'/course_category_map.txt';

$categoryservice =
    new \local_migrationtool\service\category_service();

$catmap =
    $categoryservice->restore_categories($catsfile);
/* ---------- COURSE MAP ---------- */

$map=[];

foreach(file($mapfile) as $line){

[$cid,$catid]=explode("\t",trim($line));

$map[$cid]=$catid;
}

/* ---------- RESTORE COURSES ---------- */

$courses=glob($folder.'/courses/*.mbz');

$total=count($courses);
$current=0;

echo "<progress id='progress' value='0' max='100' style='width:100%'></progress>";
echo "<div id='log'></div>";

foreach($courses as $mbz){

$current++;

preg_match('/course_(\d+)_/',basename($mbz),$m);
$cid=$m[1] ?? 0;

$oldcat=$map[$cid] ?? 1;
$categoryid=$catmap[$oldcat] ?? 1;

echo "<script>
document.getElementById('log').innerHTML+='Restore course {$cid}<br>';
document.getElementById('progress').value=".intval(($current/$total)*100).";
</script>";

flush();
ob_flush();

$cmd = PHP_BINDIR."/php ".
escapeshellarg(
$CFG->dirroot.'/admin/cli/restore_backup.php'
).
" --file=".escapeshellarg($mbz).
" --categoryid=".$categoryid.
" 2>&1";

exec($cmd,$out,$ret);

}

echo $OUTPUT->notification("Import finished",'notifysuccess');

}else{
?>

<form method="post">

<p>Ścieżka folderu migracji:</p>

<input type="text" name="folder"
style="width:100%"
placeholder="/moodledata/migration/export_123">

<br><br>

<button type="submit">
Import
</button>

</form>

<?php
}

echo $OUTPUT->footer();

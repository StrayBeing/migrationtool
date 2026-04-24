<?php
require('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

global $CFG, $DB, $OUTPUT, $PAGE;

use local_migrationtool\migration_reporter;

$PAGE->set_url('/local/migrationtool/import_zip.php');
$PAGE->set_title('Migration Tool - Import');
$PAGE->set_heading('Import kursów z ZIP');

/* 🔥 WAŻNE: live output */
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', 0);
while (ob_get_level()) { ob_end_flush(); }
ob_implicit_flush(true);

echo $OUTPUT->header();

$tmpbase = $CFG->dataroot . '/migrationtool/tmp/';
if (!is_dir($tmpbase)) {
    mkdir($tmpbase, 0775, true);
}
?>

<style>
.container{max-width:1000px;margin:auto}
.card{background:#fff;border:1px solid #ddd;border-radius:10px;padding:20px;margin-bottom:20px}
#log{
    background:#0d0d0d;
    color:#00ff88;
    font-family:monospace;
    height:320px;
    overflow:auto;
    padding:10px;
    border-radius:8px;
}
progress{width:100%;height:20px}
</style>

<div class="container">

<div class="card">
<h3>📦 Import ZIP</h3>
<form method="post" enctype="multipart/form-data">
<input type="file" name="zipfile" required>
<br><br>
<button type="submit">🚀 Import</button>
</form>
</div>

<?php 
$confirm = optional_param('confirm',0,PARAM_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!empty($_FILES['zipfile']['tmp_name']) || $confirm)):
?>
<div class="card">
<h3>📊 Postęp importu</h3>
<progress id="progress" value="0" max="100"></progress>
<div id="log"></div>
</div>

<script>
function log(msg){
    const el = document.getElementById('log');
    if(!el) return;
    el.innerHTML += msg + "<br>";
    el.scrollTop = el.scrollHeight;
}
</script>

<?php

if(!$confirm){

$tmpzip = $tmpbase . uniqid() . '.zip';
move_uploaded_file($_FILES['zipfile']['tmp_name'], $tmpzip);

}else{

$tmpzip = required_param('tmpzip',PARAM_RAW);

}
$zip = new ZipArchive();

if ($zip->open($tmpzip) !== TRUE) {
    echo "<script>log('❌ Nie można otworzyć ZIP');</script>";
    exit;
}

$tmpdir = $tmpbase . uniqid();
mkdir($tmpdir, 0775, true);

$zip->extractTo($tmpdir);
$zip->close();

echo "<script>log('📦 ZIP rozpakowany');</script>";

/* ---------------- PLUGIN CHECK ---------------- */

$pluginfile = $tmpdir.'/plugins.json';

$missing_mod=[];
$missing_qtype=[];

if(file_exists($pluginfile)){

$source=json_decode(file_get_contents($pluginfile),true);

foreach($source['mod'] as $mod){

if(!$DB->record_exists('modules',['name'=>$mod])){
$missing_mod[]=$mod;
}

}

foreach($source['qtype'] as $qt){

$qdir=$CFG->dirroot.'/question/type/'.$qt;

if(!is_dir($qdir)){
$missing_qtype[]=$qt;
}

}

}

/* ---------- STOP IF MISSING ---------- */

if((!empty($missing_mod) || !empty($missing_qtype)) && !$confirm){

echo "<div class='card' style='border:2px solid #e74c3c'>";

echo "<h3>⚠ Brakujące pluginy</h3>";

if(!empty($missing_mod)){

echo "<b>Brak aktywności:</b><br>";

foreach($missing_mod as $m){
echo "mod_{$m}<br>";
}

echo "<br>";

}

if(!empty($missing_qtype)){

echo "<b>Brak typów pytań:</b><br>";

foreach($missing_qtype as $q){
echo "qtype_{$q}<br>";
}

}

echo "<br>Import może uszkodzić quizy lub aktywności.<br><br>";

echo "<form method='post'>";

echo "<input type='hidden' name='confirm' value='1'>";
echo "<input type='hidden' name='tmpzip' value='".htmlspecialchars($tmpzip)."'>";

echo "<button class='btn btn-danger'>Continue anyway</button>";

echo "</form>";

echo "<br><a href='import_zip.php'>Cancel</a>";

echo "</div>";

echo $OUTPUT->footer();
exit;

}
/* ---------------- REPORT ---------------- */
$report = new migration_reporter();
$report->start();

/* ---------------- KATEGORIE ---------------- */
$catmap = [];
$catsfile = $tmpdir . '/moodle_categories.json';

if (file_exists($catsfile)) {

    $cats = json_decode(file_get_contents($catsfile));

    foreach ($cats as $cat) {

        $existing = $DB->get_record('course_categories', ['name' => $cat->name]);

        if ($existing) {
            $catmap[$cat->id] = $existing->id;
            echo "<script>log('📁 istnieje: {$cat->name}');</script>";
            echo str_repeat(' ', 1024); flush();
            continue;
        }

        $rec = new stdClass();
        $rec->name = $cat->name;
        $rec->parent = 0;

        $newid = $DB->insert_record('course_categories', $rec);

        $catmap[$cat->id] = $newid;

        echo "<script>log('📁 dodano: {$cat->name}');</script>";
        echo str_repeat(' ', 1024); flush();
    }
}

/* ---------------- MAPA ---------------- */
$map = [];
$mapfile = $tmpdir . '/course_category_map.txt';

if (file_exists($mapfile)) {
    foreach (file($mapfile) as $line) {
        [$cid, $catid] = explode("\t", trim($line));
        $map[$cid] = $catid;
    }
}

/* ---------------- MBZ ---------------- */
$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tmpdir)
);

$mbzfiles = [];

foreach ($rii as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'mbz') {
        $mbzfiles[] = $file->getPathname();
    }
}

$total = max(count($mbzfiles), 1);
$current = 0;

/* ---------------- RESTORE ---------------- */
foreach ($mbzfiles as $mbz) {

    $current++;

    preg_match('/course_(\d+)_/', basename($mbz), $m);
    $cid = $m[1] ?? 0;

    $oldcat = $map[$cid] ?? 1;
    $categoryid = $catmap[$oldcat] ?? 1;

    $percent = intval(($current / $total) * 100);

    echo "<script>
        document.getElementById('progress').value = {$percent};
        log('🔄 kurs {$cid}');
    </script>";
    echo str_repeat(' ', 1024);
    flush();

    $cmd = PHP_BINDIR . "/php " .
        escapeshellarg($CFG->dirroot . '/admin/cli/restore_backup.php') .
        " --file=" . escapeshellarg($mbz) .
        " --categoryid=" . intval($categoryid) .
        " 2>&1";

    exec($cmd, $output, $ret);

    if ($ret != 0) {

        $report->add($cid, 'failed', [
            'error' => implode("\n", $output)
        ]);

        echo "<script>log('❌ FAIL {$cid}');</script>";

    } else {

        $report->add($cid, 'success', [
            'category' => $categoryid
        ]);

        echo "<script>log('✔ OK {$cid}');</script>";
    }

    echo str_repeat(' ', 1024);
    flush();
}

/* ---------------- FINISH ---------------- */
$report->finish();
$file = $report->save($CFG->dataroot . '/migrationtool/reports');

$summary = $report->summary();

echo "<script>
log('📊 RAPORT zapisany');
log('OK: {$summary['success']} | FAIL: {$summary['failed']}');
log('⏱ {$summary['duration']}s');
</script>";

?>

<?php endif; ?>

</div>

<?php echo $OUTPUT->footer(); ?>

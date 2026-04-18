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

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['zipfile']['tmp_name'])): ?>

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

$tmpzip = $tmpbase . uniqid() . '.zip';
move_uploaded_file($_FILES['zipfile']['tmp_name'], $tmpzip);

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
echo str_repeat(' ', 1024); flush();

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

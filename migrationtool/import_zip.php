<?php
require('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

global $CFG, $DB, $OUTPUT, $PAGE;

use local_migrationtool\migration_reporter;

$PAGE->set_url('/local/migrationtool/import_zip.php');
$PAGE->set_title('Migration Tool - Import');
$PAGE->set_heading('Import kursów z ZIP');

/* live output */
@ini_set('output_buffering','off');
@ini_set('zlib.output_compression',0);
while (ob_get_level()) { ob_end_flush(); }
ob_implicit_flush(true);

echo $OUTPUT->header();

$tmpbase = $CFG->dataroot.'/migrationtool/tmp/';
$sessiondir = $CFG->dataroot.'/migrationtool/sessions/';

if(!is_dir($tmpbase)) mkdir($tmpbase,0775,true);
if(!is_dir($sessiondir)) mkdir($sessiondir,0775,true);

$sessionid = optional_param('sessionid','',PARAM_RAW);
$confirm = optional_param('confirm',0,PARAM_INT);
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
<input type="file" name="zipfile">
<br><br>
<button type="submit">🚀 Import</button>
</form>

</div>

<?php

/* --------- RESUME SESSION ---------- */

if(!$sessionid){

$files = glob($sessiondir.'import_*.json');

if($files){

$file = $files[0];
$sessionid = basename($file,'.json');

echo "<div class='card'>";
echo "<h3>⚠ Niedokończony import</h3>";
echo "<form method='post'>";
echo "<input type='hidden' name='sessionid' value='{$sessionid}'>";
echo "<button>Resume import</button>";
echo "</form>";
echo "</div>";

}

}

/* --------- START / RESUME ---------- */

if ($_SERVER['REQUEST_METHOD']==='POST'){

echo "<div class='card'>";
echo "<h3>📊 Postęp importu</h3>";
echo "<progress id='progress' value='0' max='100'></progress>";
echo "<div id='log'></div>";
echo "</div>";

?>

<script>
function log(msg){
let el=document.getElementById('log');
el.innerHTML+=msg+"<br>";
el.scrollTop=el.scrollHeight;
}
</script>

<?php

/* ---------- LOAD SESSION ---------- */

if($sessionid){

$sessionfile=$sessiondir.$sessionid.'.json';

$data=json_decode(file_get_contents($sessionfile),true);

$tmpzip=$data['zip'];
$tmpdir=$data['tmpdir'];
$done=$data['done'];

echo "<script>log('♻ Resume previous import')</script>";

}else{

$tmpzip=$tmpbase.uniqid().'.zip';
move_uploaded_file($_FILES['zipfile']['tmp_name'],$tmpzip);

$zip=new ZipArchive();

if($zip->open($tmpzip)!==TRUE){
echo "<script>log('❌ Cannot open ZIP')</script>";
exit;
}

$tmpdir=$tmpbase.uniqid();
mkdir($tmpdir,0775,true);

$zip->extractTo($tmpdir);
$zip->close();

echo "<script>log('📦 ZIP extracted')</script>";

$done=[];

$sessionid=uniqid('import_');
$sessionfile=$sessiondir.$sessionid.'.json';

}

/* ---------- SAVE SESSION ---------- */

file_put_contents($sessionfile,json_encode([
'zip'=>$tmpzip,
'tmpdir'=>$tmpdir,
'done'=>$done
]));

/* ---------- CATEGORY RESTORE ---------- */

$catmap=[];
$catsfile=$tmpdir.'/moodle_categories.json';

if(file_exists($catsfile)){

$cats=json_decode(file_get_contents($catsfile));

foreach($cats as $cat){

$existing=$DB->get_record('course_categories',['name'=>$cat->name]);

if($existing){
$catmap[$cat->id]=$existing->id;
continue;
}

$rec=new stdClass();
$rec->name=$cat->name;
$rec->parent=0;

$newid=$DB->insert_record('course_categories',$rec);
$catmap[$cat->id]=$newid;

}

}

/* ---------- CATEGORY MAP ---------- */

$map=[];
$mapfile=$tmpdir.'/course_category_map.txt';

if(file_exists($mapfile)){

foreach(file($mapfile) as $line){

[$cid,$catid]=explode("\t",trim($line));
$map[$cid]=$catid;

}

}

/* ---------- FIND MBZ ---------- */

$rii=new RecursiveIteratorIterator(
new RecursiveDirectoryIterator($tmpdir)
);

$mbzfiles=[];

foreach($rii as $file){

if($file->isFile() && strtolower($file->getExtension())==='mbz'){
$mbzfiles[]=$file->getPathname();
}

}

$total=count($mbzfiles);
$current=0;

/* ---------- REPORT ---------- */

$report=new migration_reporter();
$report->start();

/* ---------- RESTORE ---------- */

foreach($mbzfiles as $mbz){

preg_match('/course_(\d+)_/',basename($mbz),$m);
$cid=$m[1] ?? 0;

if(in_array($cid,$done)){
continue;
}

$current++;

$oldcat=$map[$cid] ?? 1;
$categoryid=$catmap[$oldcat] ?? 1;

$percent=intval(($current/$total)*100);

echo "<script>
document.getElementById('progress').value={$percent};
log('🔄 kurs {$cid}');
</script>";

flush();

$cmd = PHP_BINDIR."/php ".
escapeshellarg($CFG->dirroot.'/admin/cli/restore_backup.php').
" --file=".escapeshellarg($mbz).
" --categoryid=".$categoryid.
" 2>&1";

exec($cmd,$out,$ret);

if($ret!=0){

$report->add($cid,'failed',['error'=>implode("\n",$out)]);
echo "<script>log('❌ FAIL {$cid}')</script>";

}else{

$report->add($cid,'success',['category'=>$categoryid]);
echo "<script>log('✔ OK {$cid}')</script>";

}

/* SAVE PROGRESS */

$done[]=$cid;

file_put_contents($sessionfile,json_encode([
'zip'=>$tmpzip,
'tmpdir'=>$tmpdir,
'done'=>$done
]));

}

/* ---------- FINISH ---------- */

$report->finish();
$file=$report->save($CFG->dataroot.'/migrationtool/reports');

unlink($sessionfile);

echo "<script>log('📊 Import finished')</script>";

}

?>

</div>

<?php echo $OUTPUT->footer(); ?>

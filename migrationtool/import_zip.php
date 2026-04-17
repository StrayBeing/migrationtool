<?php
require('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

global $CFG,$DB,$OUTPUT,$PAGE;

$PAGE->set_url('/local/migrationtool/import_zip.php');
$PAGE->set_title('Migration Tool - Import');
$PAGE->set_heading('Import kursów z ZIP');

echo $OUTPUT->header();

$tmpbase=$CFG->dataroot.'/migrationtool/tmp/';

if(!is_dir($tmpbase)){
    mkdir($tmpbase,0775,true);
}

?>

<style>

.container{
max-width:1000px;
margin:auto;
}

.card{
background:white;
border:1px solid #ccc;
border-radius:8px;
padding:20px;
margin-bottom:20px;
}

.progressbox{
margin-top:15px;
}

#log{
background:#111;
color:#0f0;
font-family:monospace;
height:300px;
overflow:auto;
padding:10px;
border-radius:5px;
}

.status-ok{
color:#2ecc71;
font-weight:bold;
}

.status-error{
color:#e74c3c;
font-weight:bold;
}

</style>

<div class="container">

<div class="card">

<h3>Import kursów</h3>

<form method="post" enctype="multipart/form-data">

<input type="file" name="zipfile" required>

<br><br>

<button type="submit">🚀 Importuj ZIP</button>

</form>

</div>

<?php

if($_SERVER['REQUEST_METHOD']==='POST' && !empty($_FILES['zipfile']['tmp_name'])){

echo "

<div class='card'>

<h3>Postęp importu</h3>

<div class='progressbox'>
<progress id='progress' value='0' max='100' style='width:100%'></progress>
</div>

<div id='log'></div>

</div>

";

$tmpzip=$tmpbase.uniqid().'.zip';
move_uploaded_file($_FILES['zipfile']['tmp_name'],$tmpzip);

$zip=new ZipArchive();

if($zip->open($tmpzip)===TRUE){

$tmpdir=$tmpbase.uniqid();
mkdir($tmpdir,0775,true);

$zip->extractTo($tmpdir);
$zip->close();

echo "<script>
document.getElementById('log').innerHTML+='📦 ZIP rozpakowany<br>';
</script>";

flush(); ob_flush();

$catmap=[];

$catsfile=$tmpdir.'/moodle_categories.json';

if(file_exists($catsfile)){

$cats=json_decode(file_get_contents($catsfile));

foreach($cats as $cat){

$existing=$DB->get_record('course_categories',['name'=>$cat->name]);

if($existing){

$catmap[$cat->id]=$existing->id;

echo "<script>
document.getElementById('log').innerHTML+='📁 Kategoria istnieje: {$cat->name}<br>';
</script>";

continue;
}

$rec=new stdClass();
$rec->name=$cat->name;
$rec->parent=0;

$newid=$DB->insert_record('course_categories',$rec);

$catmap[$cat->id]=$newid;

echo "<script>
document.getElementById('log').innerHTML+='📁 Dodano kategorię: {$cat->name}<br>';
</script>";

flush(); ob_flush();

}

}

$map=[];
$mapfile=$tmpdir.'/course_category_map.txt';

if(file_exists($mapfile)){

$lines=file($mapfile);

foreach($lines as $line){

list($cid,$catid)=explode("\t",trim($line));

$map[$cid]=$catid;

}

}

$rii=new RecursiveIteratorIterator(
new RecursiveDirectoryIterator($tmpdir)
);

$mbzfiles=[];

foreach($rii as $file){

if($file->isFile() && strtolower($file->getExtension())=='mbz'){
$mbzfiles[]=$file->getPathname();
}

}

$total=count($mbzfiles);
$current=0;

foreach($mbzfiles as $mbz){

$current++;

preg_match('/course_(\d+)_/',basename($mbz),$m);

$cid=$m[1]??0;

$oldcat=$map[$cid]??1;
$categoryid=$catmap[$oldcat]??1;

$percent=intval(($current/$total)*100);

echo "<script>
document.getElementById('progress').value={$percent};
document.getElementById('log').innerHTML+='🔄 Przywracanie kursu {$cid}...<br>';
document.getElementById('log').scrollTop=document.getElementById('log').scrollHeight;
</script>";

flush(); ob_flush();

$cmd=PHP_BINDIR."/php ".
escapeshellarg($CFG->dirroot.'/admin/cli/restore_backup.php').
" --file=".escapeshellarg($mbz).
" --categoryid=".$categoryid.
" 2>&1";

exec($cmd,$output,$ret);

if($ret!=0){

echo "<script>
document.getElementById('log').innerHTML+='<span class=\"status-error\">❌ Błąd restore ".basename($mbz)."</span><br>';
</script>";

}else{

echo "<script>
document.getElementById('log').innerHTML+='<span class=\"status-ok\">✔ OK ".basename($mbz)."</span><br>';
</script>";

}

flush(); ob_flush();

}

echo "<script>
document.getElementById('log').innerHTML+='<br><b>🎉 Import zakończony</b>';
</script>";

}else{

echo "<div class='card'>❌ Nie można otworzyć ZIP</div>";

}

}

?>

</div>

<?php

echo $OUTPUT->footer();

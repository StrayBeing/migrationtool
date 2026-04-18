<?php
require('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

global $DB, $OUTPUT, $PAGE, $CFG;

require_once('classes/backup_manager.php');

$PAGE->set_url('/local/migrationtool/export_zip.php');
$PAGE->set_title('Migration Tool - Export');
$PAGE->set_heading('Eksport kursów do ZIP');

echo $OUTPUT->header();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $courses = required_param_array('courses', PARAM_INT);
    $manager = new \local_migrationtool\backup_manager();

    $tmpdir = $CFG->dataroot.'/migrationtool/tmp/'.uniqid();
    mkdir($tmpdir,0775,true);

    $mbzfiles = [];
    $map = [];
    $categories = [];

    $total=count($courses);
    $current=0;

    echo "<div class='progressbox'>";
    echo "<h3>Eksport kursów</h3>";
    echo "<progress id='progress' value='0' max='100' style='width:100%'></progress>";
    echo "<div id='log'></div>";
    echo "</div>";

    foreach ($courses as $cid) {

        $current++;

        $course = $DB->get_record('course',['id'=>$cid],'*',MUST_EXIST);

        $map[$cid] = $course->category;

        echo "<script>
        document.getElementById('log').innerHTML += 'Eksport: {$course->fullname}<br>';
        document.getElementById('progress').value=".intval(($current/$total)*100).";
        </script>";

        flush();
        ob_flush();

        $mbzfiles[] = $manager->export_course($cid,$tmpdir);

        $cat = $DB->get_record('course_categories',['id'=>$course->category]);

        while ($cat) {

            $categories[$cat->id] = $cat;

            if ($cat->parent == 0) break;

            $cat = $DB->get_record('course_categories',['id'=>$cat->parent]);
        }
    }

    $catsfile = $tmpdir.'/moodle_categories.json';
    file_put_contents($catsfile,json_encode($categories));

    $mapfile = $tmpdir.'/course_category_map.txt';

    $maptext="";
    foreach($map as $cid=>$catid){
        $maptext.=$cid."\t".$catid."\n";
    }

    file_put_contents($mapfile,$maptext);

$backupdir = $CFG->dataroot.'/migrationtool/backups';

if (!is_dir($backupdir)) {
    mkdir($backupdir, 0775, true);
}

$zipfile = $backupdir.'/courses_export_'.time().'.zip';

$zip = new ZipArchive();

if ($zip->open($zipfile, ZipArchive::CREATE) !== TRUE) {
    throw new moodle_exception('Cannot create ZIP file');
}
    foreach($mbzfiles as $file){
        $zip->addFile($file,"courses/".basename($file));
    }

    $zip->addFile($catsfile,"moodle_categories.json");
    $zip->addFile($mapfile,"course_category_map.txt");

    $zip->close();

    echo $OUTPUT->notification("ZIP utworzony",'notifysuccess');

    $url = new moodle_url('/local/migrationtool/download.php',
        ['file'=>basename($zipfile)]
    );

    echo html_writer::link($url,'⬇ Pobierz ZIP');

}else{

$categories=$DB->get_records('course_categories',null,'name');
$courses=$DB->get_records('course');

?>

<style>

.container{
max-width:1200px;
margin:auto;
}

.toolbar{
display:flex;
gap:10px;
margin-bottom:10px;
}

.search{
flex:1;
padding:10px;
font-size:16px;
}

.coursebox{
border:1px solid #ccc;
height:500px;
overflow:auto;
padding:10px;
background:white;
border-radius:6px;
}

.category{
font-weight:bold;
cursor:pointer;
margin-top:8px;
}

.course{
margin-left:25px;
padding:3px;
}

.listview .course{
margin-left:0;
}

.counter{
margin-left:auto;
font-weight:bold;
}

#log{
margin-top:10px;
background:#111;
color:#0f0;
font-family:monospace;
padding:10px;
height:150px;
overflow:auto;
}

</style>

<div class="container">

<h3>Eksport kursów</h3>

<div class="toolbar">

<input class="search" id="search" placeholder="Szukaj kursu...">

<button type="button" onclick="toggleView()">Zmień widok</button>

<div class="counter">
Wybrane: <span id="count">0</span>
</div>

</div>

<form method="post" id="exportForm">

<div class="coursebox" id="courseBox">

<?php

foreach($categories as $cat){

echo "<div class='category' onclick='toggleCat({$cat->id})'>
<input type='checkbox' onclick='checkCategory(event,{$cat->id})'>
📁 {$cat->name}
</div>";

foreach($courses as $course){

if($course->category==$cat->id){

if($course->id==1) continue;

echo "<div class='course cat{$cat->id}'>";

echo "<label>";

echo "<input class='coursecheck' type='checkbox' name='courses[]' value='{$course->id}'>";

echo $course->fullname;

echo "</label>";

echo "</div>";
}
}

}

?>

</div>

<br>

<button type="submit">🚀 Export ZIP</button>

</form>

</div>

<script>

function toggleCat(id){

document.querySelectorAll(".cat"+id).forEach(e=>{
e.style.display=e.style.display==="none"?"block":"none";
})

}

function toggleView(){

document.getElementById("courseBox").classList.toggle("listview")

}

document.getElementById("search").addEventListener("input",function(){

let term=this.value.toLowerCase()

document.querySelectorAll(".course").forEach(c=>{

if(c.innerText.toLowerCase().includes(term))
c.style.display="block"
else
c.style.display="none"

})

})

function checkCategory(e,id){

e.stopPropagation()

let checked=e.target.checked

document.querySelectorAll(".cat"+id+" input").forEach(c=>{
c.checked=checked
})

updateCount()

}

document.querySelectorAll(".coursecheck").forEach(c=>{
c.addEventListener("change",updateCount)
})

function updateCount(){

let n=document.querySelectorAll(".coursecheck:checked").length

document.getElementById("count").innerText=n

}

</script>

<?php

}

echo $OUTPUT->footer();

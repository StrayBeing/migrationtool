<?php
require('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

global $CFG,$DB,$OUTPUT,$PAGE;

$PAGE->set_url('/local/migrationtool/import_zip.php');
$PAGE->set_title('Import ZIP kursów');
$PAGE->set_heading('Import ZIP kursów');

echo $OUTPUT->header();

$tmpbase=$CFG->dataroot.'/migrationtool/tmp/';

if(!is_dir($tmpbase)){
    mkdir($tmpbase,0775,true);
}

if($_SERVER['REQUEST_METHOD']==='POST' && !empty($_FILES['zipfile']['tmp_name'])){

    $tmpzip=$tmpbase.uniqid().'.zip';
    move_uploaded_file($_FILES['zipfile']['tmp_name'],$tmpzip);

    $zip=new ZipArchive();

    if($zip->open($tmpzip)===TRUE){

        $tmpdir=$tmpbase.uniqid();
        mkdir($tmpdir,0775,true);

        $zip->extractTo($tmpdir);
        $zip->close();

        // ---------- restore kategorii ----------
$catmap = [];

$catsfile=$tmpdir.'/moodle_categories.json';

if(file_exists($catsfile)){

    echo html_writer::tag('h3','Przywracanie kategorii');

    $cats=json_decode(file_get_contents($catsfile));

    foreach($cats as $cat){

        // sprawdz czy kategoria o tej nazwie juz istnieje
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

        echo "Dodano kategorię {$cat->name}<br>";
    }
}
        // ---------- mapa kursów ----------

        $map=[];
        $mapfile=$tmpdir.'/course_category_map.txt';

        if(file_exists($mapfile)){

            $lines=file($mapfile);

            foreach($lines as $line){

                list($cid,$catid)=explode("\t",trim($line));

                $map[$cid]=$catid;
            }
        }

        // ---------- restore kursów ----------

        $rii=new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmpdir)
        );

        foreach($rii as $file){

            if(!$file->isFile()) continue;

            if(strtolower($file->getExtension())!='mbz') continue;

            $mbz=$file->getPathname();

            preg_match('/course_(\d+)_/',basename($mbz),$m);

            $cid=$m[1]??0;

            $oldcat = $map[$cid] ?? 1;
            $categoryid = $catmap[$oldcat] ?? 1;

            echo html_writer::tag('h4',"Przywracanie kursu {$cid}");

            $cmd=PHP_BINDIR."/php ".
                escapeshellarg($CFG->dirroot.'/admin/cli/restore_backup.php').
                " --file=".escapeshellarg($mbz).
                " --categoryid=".$categoryid.
                " 2>&1";

            exec($cmd,$output,$ret);

            echo html_writer::tag('pre',implode("\n",$output));

            if($ret!=0){
                echo $OUTPUT->notification("Błąd restore ".basename($mbz),'notifyproblem');
            }else{
                echo $OUTPUT->notification("OK ".basename($mbz),'notifysuccess');
            }
        }

    }else{
        echo $OUTPUT->notification('Nie można otworzyć ZIP','notifyproblem');
    }
}

echo html_writer::start_tag('form',['method'=>'post','enctype'=>'multipart/form-data']);

echo "Wybierz ZIP:<br>";

echo html_writer::empty_tag('input',['type'=>'file','name'=>'zipfile','required'=>true]);

echo "<br><br>";

echo html_writer::empty_tag('input',['type'=>'submit','value'=>'Importuj']);

echo html_writer::end_tag('form');

echo $OUTPUT->footer();

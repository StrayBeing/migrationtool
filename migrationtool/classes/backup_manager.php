<?php
namespace local_migrationtool;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/backup/util/includes/backup_includes.php');

class backup_manager {

    /**
     * Eksport pojedynczego kursu do pliku .mbz
     */
public function export_course($courseid, $tmpdir) {
    global $USER;

    $bc = new \backup_controller(
        \backup::TYPE_1COURSE,
        $courseid,
        \backup::FORMAT_MOODLE,
        \backup::INTERACTIVE_NO,
        \backup::MODE_GENERAL,
        $USER->id
    );

    $bc->execute_plan();
    $results = $bc->get_results();
    $file = $results['backup_destination'];

    // Zapisz mbz w katalogu tymczasowym z unikalną nazwą
    $mbzpath = $tmpdir.'/course_'.$courseid.'_'.uniqid().'.mbz';
    file_put_contents($mbzpath, $file->get_content());

    return $mbzpath;
}
    /**
     * Eksport wielu kursów do jednego ZIP
     */
    public function export_courses_zip(array $courseids) {
        global $CFG;

        $tempdir = $CFG->dataroot.'/migrationtool/tmp/'.uniqid();
        if (!is_dir($tempdir)) {
            mkdir($tempdir, 0775, true);
        }

        $mbzfiles = [];

        foreach ($courseids as $cid) {
            $mbz = $this->export_course($cid);
            $mbzfiles[] = $mbz;
        }

        $zipfile = $CFG->dataroot.'/migrationtool/backups/courses_export_'.time().'.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipfile, \ZipArchive::CREATE) !== true) {
            throw new \moodle_exception('Cannot create ZIP file');
        }

        foreach ($mbzfiles as $mbz) {
            $zip->addFile($mbz, basename($mbz));
        }

        $zip->close();

        return $zipfile;
    }
}

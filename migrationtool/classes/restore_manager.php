<?php

namespace local_migrationtool;
use ZipArchive;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/backup/util/includes/restore_includes.php');

class restore_manager {

    /**
     * Import pojedynczego backupu .mbz do wskazanej kategorii
     *
     * @param string $filepath - pełna ścieżka do pliku .mbz
     * @param int $categoryid - ID kategorii docelowej
     * @return bool
     * @throws \coding_exception
     */
    public function import_single_backup($filepath, $categoryid) {
        global $USER, $CFG;

        if (!file_exists($filepath)) {
            throw new \coding_exception("Nie znaleziono backupu: $filepath");
        }

        // katalog tymczasowy dla restore
        $tempdir = 'migrationtool_restore_' . uniqid();
        $fulltemp = $CFG->dataroot.'/temp/backup/'.$tempdir;
        if (!is_dir($fulltemp)) {
            mkdir($fulltemp, 0777, true);
        }

        // rozpakowanie backupu do katalogu tymczasowego
        $packer = get_file_packer('application/vnd.moodle.backup');
        $packer->extract_to_pathname($filepath, $fulltemp);

        // utworzenie restore_controller na folderze tymczasowym
        $rc = new \restore_controller(
            $tempdir,
            $categoryid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );

        // precheck
        $rc->execute_precheck();

        // ustawienia restore (MVP: nie przywracamy użytkowników ani grup)
        $plan = $rc->get_plan();
        foreach ($plan->get_settings() as $setting) {
            if (!method_exists($setting, 'set_value')) continue;
            if ($setting->get_status() != \backup_setting::NOT_LOCKED) continue;

            $name = $setting->get_name();
            switch ($name) {
                case 'users':
                case 'groups':
                case 'role_assignments':
                case 'completion':
                    $setting->set_value(false);
                    break;
                default:
                    $setting->set_value(true);
            }
        }

        // wykonanie restore
        $rc->set_execution(\backup::EXECUTION_INMEDIATE);
        $rc->execute_plan();
        $rc->destroy();

        return true;
    }
public function import_zip($zipfile, $categoryid) {
    global $CFG;

    $zip = new \ZipArchive();
    if ($zip->open($zipfile) !== TRUE) {
        throw new \moodle_exception('Cannot open ZIP file');
    }

    $tmpzipdir = $CFG->dataroot.'/migrationtool/tmp/'.uniqid();
    mkdir($tmpzipdir, 0775, true);
    $zip->extractTo($tmpzipdir);
    $zip->close();

    $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tmpzipdir));
    foreach ($rii as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'mbz') continue;

        // kopiujemy MBZ do folderu temp/backup/
        $backupfilepath = $CFG->dataroot.'/temp/backup/'.basename($file);
        copy($file->getPathname(), $backupfilepath);

        // i wywołujemy działający import_single_backup() na tym pliku
        $this->import_single_backup($backupfilepath, $categoryid);
    }

    return true;
}
}

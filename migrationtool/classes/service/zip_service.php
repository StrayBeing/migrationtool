<?php

namespace local_migrationtool\service;

defined('MOODLE_INTERNAL') || die();

class zip_service {

    public function create_zip(
        string $zipfile,
        string $tmpdir,
        array $files
    ): void {

        $zip = new \ZipArchive();

        if ($zip->open($zipfile, \ZipArchive::CREATE) !== true) {
            throw new \moodle_exception('Cannot create ZIP file');
        }

        foreach (glob($tmpdir . "/*.mbz") as $file) {
            $zip->addFile($file, "courses/" . basename($file));
        }

        foreach ($files as $inside => $realpath) {
            $zip->addFile($realpath, $inside);
        }

        $zip->close();
    }
}

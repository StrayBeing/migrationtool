<?php

namespace local_migrationtool\service;

defined('MOODLE_INTERNAL') || die();

class export_metadata_service {

    public function save_categories(
        string $filepath,
        array $categories
    ): void {

        file_put_contents(
            $filepath,
            json_encode($categories)
        );
    }

    public function save_course_map(
        string $filepath,
        array $map
    ): void {

        $text = "";

        foreach ($map as $cid => $catid) {
            $text .= $cid . "\t" . $catid . "\n";
        }

        file_put_contents($filepath, $text);
    }

    public function save_migration_info(
        string $filepath,
        int $courses
    ): void {

        file_put_contents(
            $filepath,
            json_encode([
                'courses' => $courses,
                'date' => date('c')
            ])
        );
    }
}

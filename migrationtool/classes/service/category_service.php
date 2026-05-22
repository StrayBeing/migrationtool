<?php

namespace local_migrationtool\service;

defined('MOODLE_INTERNAL') || die();

class category_service {

    public function restore_categories(string $catsfile): array {

        global $DB;

        $catmap = [];

        if (!file_exists($catsfile)) {
            return $catmap;
        }

        $cats = json_decode(file_get_contents($catsfile));

        foreach ($cats as $cat) {

            $existing = $DB->get_record(
                'course_categories',
                ['name' => $cat->name]
            );

            if ($existing) {

                $catmap[$cat->id] = $existing->id;
                continue;
            }

            $rec = new \stdClass();
            $rec->name = $cat->name;
            $rec->parent = 0;

            $newid = $DB->insert_record(
                'course_categories',
                $rec
            );

            $catmap[$cat->id] = $newid;
        }

        return $catmap;
    }
}

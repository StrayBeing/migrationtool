<?php

namespace local_migrationtool\service;

defined('MOODLE_INTERNAL') || die();

class plugin_export_service {

    public function export_plugins(string $filepath): void {

        global $DB, $CFG;

        $plugins = [
            'mod' => [],
            'qtype' => []
        ];

        $mods = $DB->get_records('modules');

        foreach ($mods as $mod) {
            $plugins['mod'][] = $mod->name;
        }

        $qtypedirs = glob(
            $CFG->dirroot . '/question/type/*',
            GLOB_ONLYDIR
        );

        foreach ($qtypedirs as $dir) {

            $name = basename($dir);

            if ($name === 'random' ||
                $name === 'missingtype') {
                continue;
            }

            $plugins['qtype'][] = $name;
        }

        file_put_contents(
            $filepath,
            json_encode($plugins, JSON_PRETTY_PRINT)
        );
    }
}

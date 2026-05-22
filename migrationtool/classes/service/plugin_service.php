<?php

namespace local_migrationtool\service;

defined('MOODLE_INTERNAL') || die();

class plugin_service {

    public function check_plugins(string $pluginfile): array {

        global $DB, $CFG;

        $missing_mod = [];
        $missing_qtype = [];

        if (!file_exists($pluginfile)) {
            return [$missing_mod, $missing_qtype];
        }

        $source = json_decode(
            file_get_contents($pluginfile),
            true
        );

        foreach ($source['mod'] as $mod) {

            $exists = $DB->record_exists(
                'modules',
                ['name' => $mod]
            );

            if (!$exists) {
                $missing_mod[] = $mod;
            }
        }

        foreach ($source['qtype'] as $qt) {

            $qdir = $CFG->dirroot . '/question/type/' . $qt;

            if (!is_dir($qdir)) {
                $missing_qtype[] = $qt;
            }
        }

        return [$missing_mod, $missing_qtype];
    }
}

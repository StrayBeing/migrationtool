<?php

namespace local_migrationtool;

defined('MOODLE_INTERNAL') || die();

class backup_manager {
    private const EXCLUDED_SETTINGS = [
        'anonymize',
        'role_assignments',
        'comments',
        'calendarevents',
        'userscompletion',
        'logs',
        'grade_histories',
        'groups',
        'xapistate',
        'users',
    ];

    public function export_course(int $courseid, string $destinationdir): array {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        make_writable_directory($destinationdir);

        $controller = null;
        $started = microtime(true);
        try {
            $controller = new \backup_controller(
                \backup::TYPE_1COURSE,
                $courseid,
                \backup::FORMAT_MOODLE,
                \backup::INTERACTIVE_NO,
                \backup::MODE_GENERAL,
                $USER->id,
                \backup::RELEASESESSION_NO
            );

            $this->exclude_user_data($controller);
            $controller->execute_plan();
            $results = $controller->get_results();
            if (empty($results['backup_destination']) || !($results['backup_destination'] instanceof \stored_file)) {
                throw new \moodle_exception('backupfailed', 'backup');
            }

            $filename = 'course_' . $courseid . '.mbz';
            $filepath = $destinationdir . '/' . $filename;
            $results['backup_destination']->copy_content_to($filepath);
            if (!is_readable($filepath) || filesize($filepath) === 0) {
                throw new \moodle_exception('backupfailed', 'backup');
            }

            return [
                'filename' => $filename,
                'filepath' => $filepath,
                'sha256' => hash_file('sha256', $filepath),
                'size' => filesize($filepath),
                'duration' => round(microtime(true) - $started, 3),
            ];
        } finally {
            if ($controller) {
                $controller->destroy();
            }
        }
    }

    private function exclude_user_data(\backup_controller $controller): void {
        $settings = [];
        foreach ($controller->get_plan()->get_settings() as $setting) {
            $settings[$setting->get_name()] = $setting;
        }

        foreach (self::EXCLUDED_SETTINGS as $name) {
            if (!isset($settings[$name])) {
                continue;
            }
            if ($settings[$name]->get_status() === \base_setting::NOT_LOCKED) {
                $settings[$name]->set_value(false);
            }
        }

        foreach (self::EXCLUDED_SETTINGS as $name) {
            if (isset($settings[$name]) && (bool)$settings[$name]->get_value()) {
                throw new \moodle_exception('generalexceptionmessage', 'error', '',
                    'The backup setting "' . $name . '" could not be disabled.');
            }
        }
    }
}

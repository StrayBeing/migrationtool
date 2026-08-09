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

    public function export_course(int $courseid, string $destinationdir, array $scope): array {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        make_writable_directory($destinationdir);
        $scope = \local_migrationtool\service\scope_service::normalise($scope);

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

            $this->configure_settings($controller, $scope);
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

    private function configure_settings(\backup_controller $controller, array $scope): void {
        $settings = [];
        foreach ($controller->get_plan()->get_settings() as $setting) {
            $settings[$setting->get_name()] = $setting;
        }

        //user data not in
        foreach (self::EXCLUDED_SETTINGS as $name) {
            $this->set_setting($settings, $name, false, false);
        }

        foreach (\local_migrationtool\service\scope_service::moodle_settings($scope) as $name => $value) {
            $allowmissingwhenenabled = $name === 'questionbank';
            $this->set_setting($settings, $name, $value, true, $allowmissingwhenenabled);
        }
    }

    private function set_setting(array $settings, string $name, bool $value, bool $required,
            bool $allowmissingwhenenabled = false): void {
        if (!isset($settings[$name])) {
            if ($allowmissingwhenenabled && $value) {
                return;
            }
            if ($required) {
                throw new \moodle_exception('generalexceptionmessage', 'error', '',
                    get_string('backupsettingunavailable', 'local_migrationtool', $name));
            }
            return;
        }

        $setting = $settings[$name];
        if ($setting->get_status() === \base_setting::NOT_LOCKED) {
            $setting->set_value($value);
        }

        if ((bool)$setting->get_value() !== $value) {
            throw new \moodle_exception('generalexceptionmessage', 'error', '',
                get_string('backupsettingnotapplied', 'local_migrationtool', $name));
        }
    }
}

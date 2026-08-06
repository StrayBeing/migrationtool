<?php

namespace local_migrationtool;

defined('MOODLE_INTERNAL') || die();

class restore_manager {
    private const EXCLUDED_SETTINGS = [
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

    public function restore_course(string $filepath, int $categoryid): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/course/lib.php');

        if (!is_readable($filepath)) {
            throw new \moodle_exception('filenotfound', 'error');
        }
        \core_course_category::get($categoryid, MUST_EXIST, true);

        $started = microtime(true);
        $tempdirname = \restore_controller::get_tempdir_name(SITEID, $USER->id);
        $temppath = make_backup_temp_directory($tempdirname);
        $controller = null;
        $courseid = 0;
        $precheck = [];

        try {
            $packer = get_file_packer('application/vnd.moodle.backup');
            if (!$packer->extract_to_pathname($filepath, $temppath)) {
                throw new \moodle_exception('invalidrestorefile', 'backup');
            }

            $info = \backup_general_helper::get_backup_information($tempdirname);
            $fullname = $info->original_course_fullname ?? get_string('restoringcourse', 'backup');
            $shortname = $info->original_course_shortname ?? get_string('restoringcourseshortname', 'backup');
            [$fullname, $shortname] = \restore_dbops::calculate_course_names(0, $fullname, $shortname);
            $courseid = (int)\restore_dbops::create_new_course($fullname, $shortname, $categoryid);

            $controller = new \restore_controller(
                $tempdirname,
                $courseid,
                \backup::INTERACTIVE_NO,
                \backup::MODE_GENERAL,
                $USER->id,
                \backup::TARGET_NEW_COURSE,
                null,
                \backup::RELEASESESSION_NO
            );
            $this->exclude_user_data($controller);

            $passed = $controller->execute_precheck();
            $precheck = $controller->get_precheck_results();
            if (!$passed && !empty($precheck['errors'])) {
                throw new \moodle_exception('generalexceptionmessage', 'error', '',
                    'Restore precheck failed: ' . implode('; ', $this->flatten_messages($precheck['errors'])));
            }

            $controller->execute_plan();
            return [
                'status' => 'success',
                'target_course_id' => $courseid,
                'target_category_id' => $categoryid,
                'duration' => round(microtime(true) - $started, 3),
                'precheck_warnings' => $this->flatten_messages($precheck['warnings'] ?? []),
            ];
        } catch (\Throwable $e) {
            if ($courseid > 0 && $DB->record_exists('course', ['id' => $courseid])) {
                try {
                    delete_course($courseid, false);
                } catch (\Throwable $cleanup) {
                    debugging('Could not remove failed restored course ' . $courseid . ': ' . $cleanup->getMessage(), DEBUG_DEVELOPER);
                }
            }
            throw $e;
        } finally {
            if ($controller) {
                $controller->destroy();
            }
            if (is_dir($temppath)) {
                fulldelete($temppath);
            }
        }
    }

    private function exclude_user_data(\restore_controller $controller): void {
        $settings = [];
        foreach ($controller->get_plan()->get_settings() as $setting) {
            $settings[$setting->get_name()] = $setting;
        }
        foreach (self::EXCLUDED_SETTINGS as $name) {
            if (isset($settings[$name]) && $settings[$name]->get_status() === \base_setting::NOT_LOCKED) {
                $settings[$name]->set_value(false);
            }
        }
        foreach (self::EXCLUDED_SETTINGS as $name) {
            if (isset($settings[$name]) && (bool)$settings[$name]->get_value()) {
                throw new \moodle_exception('generalexceptionmessage', 'error', '',
                    'The restore setting "' . $name . '" could not be disabled.');
            }
        }
    }

    private function flatten_messages($messages): array {
        if (!is_array($messages)) {
            $messages = [$messages];
        }
        $result = [];
        array_walk_recursive($messages, static function($value) use (&$result): void {
            if (is_scalar($value) && (string)$value !== '') {
                $result[] = (string)$value;
            }
        });
        return array_values(array_unique($result));
    }
}

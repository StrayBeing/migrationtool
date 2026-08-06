<?php
//json creator for reports
namespace local_migrationtool\service;

defined('MOODLE_INTERNAL') || die();

class manifest_service {
    public const SCHEMA_VERSION = 1;

    public function build(array $courseids, array $backups): array {
        global $CFG, $DB;

        $categoryservice = new category_service();
        $pluginservice = new plugin_service();
        $categories = $categoryservice->export_for_courses($courseids);
        $components = $pluginservice->collect_for_courses($courseids);

        $courses = [];
        foreach ($courseids as $courseid) {
            $courseid = (int)$courseid;
            $course = $DB->get_record('course', ['id' => $courseid],
                'id,fullname,shortname,category,format,visible,startdate,enddate', MUST_EXIST);
            if (!isset($backups[$courseid])) {
                throw new \moodle_exception('invaliddata', 'error');
            }
            $backup = $backups[$courseid];
            $courses[] = [
                'source_id' => $courseid,
                'fullname' => $course->fullname,
                'shortname' => $course->shortname,
                'category_id' => (int)$course->category,
                'format' => $course->format,
                'visible' => (int)$course->visible,
                'startdate' => (int)$course->startdate,
                'enddate' => (int)$course->enddate,
                'backup_file' => 'courses/' . $backup['filename'],
                'sha256' => $backup['sha256'],
                'size' => (int)$backup['size'],
                'export_duration' => $backup['duration'],
            ];
        }

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'created_at' => date('c'),
            'generator' => [
                'component' => 'local_migrationtool',
                'version' => 2026080600,
                'release' => '0.2.0',
            ],
            'source' => [
                'moodle_version' => (string)$CFG->version,
                'moodle_release' => (string)$CFG->release,
                'moodle_branch' => (string)$CFG->branch,
                'php_version' => PHP_VERSION,
                'site_hash' => hash('sha256', (string)$CFG->wwwroot),
            ],
            'scope' => [
                'course_structure' => true,
                'activities' => true,
                'files' => true,
                'question_bank' => true,
                'groups' => false,
                'users' => false,
                'enrolments' => false,
                'role_assignments' => false,
                'user_completion' => false,
                'logs' => false,
                'comments' => false,
                'grade_histories' => false,
                'xapi_user_state' => false,
            ],
            'courses' => $courses,
            'categories' => $categories,
            'components' => $components,
        ];
    }

    public function save(string $path, array $manifest): void {
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \moodle_exception('errorwhilesaving', 'error');
        }
    }
}

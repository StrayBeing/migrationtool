<?php
//simulation, for now only focused on 4.5 to 5.0. Add later more versions if needed.
namespace local_migrationtool\service;

defined('MOODLE_INTERNAL') || die();

class preflight_service {
    public function analyse(array $manifest, string $packagedir): array {
        global $CFG;

        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $errors = [];
        $warnings = [];
        $checks = [];

        $sourcebranch = (int)($manifest['source']['moodle_branch'] ?? 0);
        $targetbranch = (int)$CFG->branch;
        if (!in_array($sourcebranch, [405, 500], true)) {
            $errors[] = 'The source Moodle branch is outside the supported project scope (4.5 and 5.0).';
        }
        if (!in_array($targetbranch, [405, 500], true)) {
            $errors[] = 'The target Moodle branch is outside the supported project scope (4.5 and 5.0).';
        }
        if ($sourcebranch > $targetbranch) {
            $errors[] = 'Restoring a package from a newer Moodle branch to an older branch is blocked.';
        } else if ($sourcebranch !== $targetbranch) {
            $warnings[] = 'The Moodle branches differ. The package will be restored from Moodle ' .
                ($manifest['source']['moodle_release'] ?? $sourcebranch) . ' to Moodle ' . $CFG->release . '.';
        }
        $checks['core'] = [
            'status' => empty($errors) ? (empty($warnings) ? 'ok' : 'warning') : 'error',
            'source_version' => $manifest['source']['moodle_version'] ?? null,
            'source_release' => $manifest['source']['moodle_release'] ?? null,
            'source_branch' => $sourcebranch,
            'target_version' => (string)$CFG->version,
            'target_release' => (string)$CFG->release,
            'target_branch' => $targetbranch,
        ];

        $pluginservice = new plugin_service();
        $pluginresults = $pluginservice->compare($manifest['components']);
        foreach ($pluginresults as $result) {
            if ($result['status'] === 'error') {
                $errors[] = $result['component'] . ': ' . $result['message'];
            } else if ($result['status'] === 'warning') {
                $warnings[] = $result['component'] . ': ' . $result['message'];
            }
        }
        $checks['components'] = $pluginresults;

        $categoryservice = new category_service();
        $categoryplan = $categoryservice->plan($manifest['categories']);
        foreach ($categoryplan['items'] as $item) {
            if ($item['status'] === 'error') {
                $errors[] = 'Category ' . $item['name'] . ': ' . $item['message'];
            }
        }
        $checks['categories'] = $categoryplan['items'];

        $backupchecks = [];
        foreach ($manifest['courses'] as $course) {
            $path = $packagedir . '/' . $course['backup_file'];
            try {
                $info = \backup_general_helper::get_backup_information_from_mbz($path);
                $backupcheck = [
                    'source_id' => (int)$course['source_id'],
                    'fullname' => $course['fullname'],
                    'file' => $course['backup_file'],
                    'status' => 'ok',
                    'backup_moodle_version' => (string)$info->moodle_version,
                    'backup_moodle_release' => (string)$info->moodle_release,
                    'backup_type' => $info->type ?? null,
                    'original_course_id' => $info->original_course_id ?? null,
                ];
                if ((string)$info->moodle_version !== (string)($manifest['source']['moodle_version'] ?? '')) {
                    $backupcheck['status'] = 'warning';
                    $backupcheck['message'] = 'The MBZ Moodle version differs from the package manifest.';
                    $warnings[] = $course['fullname'] . ': ' . $backupcheck['message'];
                }
            } catch (\Throwable $e) {
                $backupcheck = [
                    'source_id' => (int)$course['source_id'],
                    'fullname' => $course['fullname'],
                    'file' => $course['backup_file'],
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
                $errors[] = $course['fullname'] . ': the MBZ file could not be read (' . $e->getMessage() . ').';
            }
            $backupchecks[] = $backupcheck;
        }
        $checks['backups'] = $backupchecks;

        $status = !empty($errors) ? 'error' : (!empty($warnings) ? 'warning' : 'ok');
        return [
            'type' => 'simulation',
            'status' => $status,
            'can_migrate' => empty($errors),
            'created_at' => date('c'),
            'created_at_ts' => time(),
            'source' => $manifest['source'],
            'target' => [
                'moodle_version' => (string)$CFG->version,
                'moodle_release' => (string)$CFG->release,
                'moodle_branch' => (string)$CFG->branch,
                'php_version' => PHP_VERSION,
            ],
            'scope' => $manifest['scope'],
            'course_count' => count($manifest['courses']),
            'errors' => $errors,
            'warnings' => $warnings,
            'checks' => $checks,
        ];
    }
}

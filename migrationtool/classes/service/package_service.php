<?php

namespace local_migrationtool\service;

defined('MOODLE_INTERNAL') || die();

class package_service {
    public function extract(string $zipfile, string $destination): array {
        $zipservice = new zip_service();
        $entries = $zipservice->validate_and_extract($zipfile, $destination);
        $manifest = $this->load_manifest($destination . '/manifest.json');
        $this->validate_manifest($manifest, $destination, $entries);
        return $manifest;
    }

    public function load_manifest(string $manifestpath): array {
        if (!is_readable($manifestpath)) {
            throw new \moodle_exception('invalidpackage', 'local_migrationtool');
        }
        $manifest = json_decode((string)file_get_contents($manifestpath), true);
        if (!is_array($manifest)) {
            throw new \moodle_exception('invalidpackage', 'local_migrationtool');
        }
        return $manifest;
    }

    private function validate_manifest(array $manifest, string $rootdir, array $entries): void {
        if ((int)($manifest['schema_version'] ?? 0) !== manifest_service::SCHEMA_VERSION ||
                empty($manifest['source']) || empty($manifest['scope']) || empty($manifest['courses']) ||
                !isset($manifest['categories']) || !isset($manifest['components'])) {
            throw new \moodle_exception('invalidpackage', 'local_migrationtool');
        }

        $requiredfalse = ['users', 'enrolments', 'role_assignments', 'user_completion', 'logs',
            'comments', 'grade_histories', 'xapi_user_state'];
        foreach ($requiredfalse as $key) {
            if (!array_key_exists($key, $manifest['scope']) || (bool)$manifest['scope'][$key]) {
                throw new \moodle_exception('invalidpackage', 'local_migrationtool');
            }
        }

        $listed = [];
        foreach ($manifest['courses'] as $course) {
            $relative = (string)($course['backup_file'] ?? '');
            if (!preg_match('#^courses/[A-Za-z0-9_.-]+\.mbz$#', $relative) || isset($listed[$relative])) {
                throw new \moodle_exception('invalidpackage', 'local_migrationtool');
            }
            $listed[$relative] = true;
            $path = $rootdir . '/' . $relative;
            if (!is_readable($path)) {
                throw new \moodle_exception('invalidpackage', 'local_migrationtool');
            }
            $expected = strtolower((string)($course['sha256'] ?? ''));
            if (!preg_match('/^[a-f0-9]{64}$/', $expected) || !hash_equals($expected, hash_file('sha256', $path))) {
                throw new \moodle_exception('invalidpackage', 'local_migrationtool');
            }
            if ((int)($course['size'] ?? -1) !== filesize($path)) {
                throw new \moodle_exception('invalidpackage', 'local_migrationtool');
            }
        }

        foreach ($entries as $entry) {
            if (preg_match('#^courses/.+\.mbz$#', $entry) && !isset($listed[$entry])) {
                throw new \moodle_exception('invalidpackage', 'local_migrationtool');
            }
        }
    }
}

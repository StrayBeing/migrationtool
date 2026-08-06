<?php
//zip creator, max 5GBs of courses only, better way in other version
namespace local_migrationtool\service;

defined('MOODLE_INTERNAL') || die();

class zip_service {
    private const MAX_ENTRIES = 5000;
    private const MAX_UNCOMPRESSED_BYTES = 5368709120; // 5 GiB.
    private const MAX_RATIO = 250;

    public function create(string $zipfile, string $rootdir, array $relativefiles): void {
        $zip = new \ZipArchive();
        $result = $zip->open($zipfile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new \moodle_exception('cannotcreatezip', 'error');
        }

        foreach ($relativefiles as $relative) {
            $relative = $this->normalise_name($relative);
            $absolute = $rootdir . '/' . $relative;
            if (!is_readable($absolute) || !$zip->addFile($absolute, $relative)) {
                $zip->close();
                throw new \moodle_exception('filenotfound', 'error', '', $relative);
            }
        }
        $zip->close();
    }

    public function validate_and_extract(string $zipfile, string $destination): array {
        $zip = new \ZipArchive();
        if ($zip->open($zipfile) !== true) {
            throw new \moodle_exception('invalidpackage', 'local_migrationtool');
        }

        if ($zip->numFiles < 2 || $zip->numFiles > self::MAX_ENTRIES) {
            $zip->close();
            throw new \moodle_exception('invalidpackage', 'local_migrationtool');
        }

        $entries = [];
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat || !isset($stat['name'])) {
                $zip->close();
                throw new \moodle_exception('invalidpackage', 'local_migrationtool');
            }
            $name = $this->normalise_name($stat['name']);
            $this->validate_entry_name($name);

            $isdir = str_ends_with($name, '/');
            if (!$isdir && $name !== 'manifest.json' && !preg_match('#^courses/[A-Za-z0-9_.-]+\.mbz$#', $name)) {
                $zip->close();
                throw new \moodle_exception('invalidpackage', 'local_migrationtool');
            }

            $size = (int)($stat['size'] ?? 0);
            $compressed = (int)($stat['comp_size'] ?? 0);
            $total += $size;
            if ($total > self::MAX_UNCOMPRESSED_BYTES) {
                $zip->close();
                throw new \moodle_exception('invalidpackage', 'local_migrationtool');
            }
            if ($compressed > 0 && $size / $compressed > self::MAX_RATIO) {
                $zip->close();
                throw new \moodle_exception('invalidpackage', 'local_migrationtool');
            }
            $entries[] = $name;
        }

        if (!in_array('manifest.json', $entries, true)) {
            $zip->close();
            throw new \moodle_exception('invalidpackage', 'local_migrationtool');
        }

        make_writable_directory($destination);
        if (!$zip->extractTo($destination)) {
            $zip->close();
            throw new \moodle_exception('invalidpackage', 'local_migrationtool');
        }
        $zip->close();
        return $entries;
    }

    private function normalise_name(string $name): string {
        return str_replace('\\', '/', $name);
    }

    private function validate_entry_name(string $name): void {
        if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:\//', $name)) {
            throw new \moodle_exception('invalidpackage', 'local_migrationtool');
        }
        foreach (explode('/', rtrim($name, '/')) as $part) {
            if ($part === '..' || $part === '.') {
                throw new \moodle_exception('invalidpackage', 'local_migrationtool');
            }
        }
    }
}

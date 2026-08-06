<?php

namespace local_migrationtool\service;

defined('MOODLE_INTERNAL') || die();

class storage_service {
    public static function base_dir(): string {
        global $CFG;
        $dir = $CFG->dataroot . '/local_migrationtool';
        make_writable_directory($dir);
        return $dir;
    }

    public static function packages_dir(): string {
        $dir = self::base_dir() . '/packages';
        make_writable_directory($dir);
        return $dir;
    }

    public static function jobs_dir(): string {
        $dir = self::base_dir() . '/jobs';
        make_writable_directory($dir);
        return $dir;
    }

    public static function reports_dir(): string {
        $dir = self::base_dir() . '/reports';
        make_writable_directory($dir);
        return $dir;
    }

    public static function create_job_dir(string $jobid): string {
        self::validate_id($jobid);
        $dir = self::jobs_dir() . '/' . $jobid;
        make_writable_directory($dir);
        return $dir;
    }

    public static function validate_id(string $id): void {
        if (!preg_match('/^[a-zA-Z0-9_-]{8,80}$/', $id)) {
            throw new \invalid_parameter_exception('Invalid identifier.');
        }
    }

    public static function new_id(string $prefix): string {
        return $prefix . '_' . bin2hex(random_bytes(16));
    }

    public static function remove_tree(string $path): void {
        if (is_dir($path)) {
            fulldelete($path);
        } else if (is_file($path)) {
            @unlink($path);
        }
    }
}

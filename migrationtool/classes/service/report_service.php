<?php

namespace local_migrationtool\service;

defined('MOODLE_INTERNAL') || die();

class report_service {
    public function save(array $report): string {
        $reportid = $report['report_id'] ?? storage_service::new_id('report');
        storage_service::validate_id($reportid);
        $report['report_id'] = $reportid;
        $path = storage_service::reports_dir() . '/' . $reportid . '.json';
        $this->write_json($path, $report);
        return $reportid;
    }

    public function load(string $reportid): array {
        storage_service::validate_id($reportid);
        $path = storage_service::reports_dir() . '/' . $reportid . '.json';
        if (!is_readable($path)) {
            throw new \moodle_exception('filenotfound', 'error');
        }
        return $this->read_json($path);
    }

    public function list(): array {
        $reports = [];
        foreach (glob(storage_service::reports_dir() . '/*.json') ?: [] as $path) {
            try {
                $report = $this->read_json($path);
                $report['_path'] = $path;
                $reports[] = $report;
            } catch (\Throwable $e) {
                debugging('Invalid migration report: ' . $path . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        usort($reports, static function(array $a, array $b): int {
            return ($b['created_at_ts'] ?? 0) <=> ($a['created_at_ts'] ?? 0);
        });
        return $reports;
    }

    public function save_job(array $job): void {
        storage_service::validate_id($job['job_id']);
        $dir = storage_service::create_job_dir($job['job_id']);
        $this->write_json($dir . '/job.json', $job);
    }

    public function load_job(string $jobid, int $userid): array {
        storage_service::validate_id($jobid);
        $path = storage_service::jobs_dir() . '/' . $jobid . '/job.json';
        if (!is_readable($path)) {
            throw new \moodle_exception('jobnotfound', 'local_migrationtool');
        }
        $job = $this->read_json($path);
        if ((int)($job['user_id'] ?? 0) !== $userid) {
            throw new \moodle_exception('jobnotfound', 'local_migrationtool');
        }
        return $job;
    }

    private function write_json(string $path, array $data): void {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \moodle_exception('errorwhilesaving', 'error');
        }
    }

    private function read_json(string $path): array {
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data)) {
            throw new \moodle_exception('invaliddata', 'error');
        }
        return $data;
    }
}

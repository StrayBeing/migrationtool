<?php

namespace local_migrationtool;

class migration_reporter {

    private $session;

    public function start() {
        $this->session = [
            'started' => time(),
            'courses' => [],
        ];
    }

    public function add($courseid, $status, $data = []) {
        $this->session['courses'][] = [
            'courseid' => $courseid,
            'status' => $status,
            'data' => $data,
            'time' => time()
        ];
    }

    public function finish() {
        $this->session['finished'] = time();
    }

    public function save($dir) {

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $file = $dir.'/report_'.time().'.json';

        file_put_contents($file, json_encode($this->session, JSON_PRETTY_PRINT));

        return $file;
    }

    public function summary() {

        $ok = 0;
        $fail = 0;

        foreach ($this->session['courses'] as $c) {
            if ($c['status'] === 'success') $ok++;
            else $fail++;
        }

        return [
            'total' => count($this->session['courses']),
            'success' => $ok,
            'failed' => $fail,
            'duration' => ($this->session['finished'] ?? time()) - $this->session['started']
        ];
    }
}

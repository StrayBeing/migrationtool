<?php

require('../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/migrationtool:manage', $context);
require_once(__DIR__ . '/forms/import_form.php');

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/migrationtool/import.php'));
$PAGE->set_title(get_string('import', 'local_migrationtool'));
$PAGE->set_heading(get_string('import', 'local_migrationtool'));

$action = optional_param('action', '', PARAM_ALPHA);
$jobid = optional_param('jobid', '', PARAM_ALPHANUMEXT);
$reportservice = new \local_migrationtool\service\report_service();

if ($action === 'execute') {
    require_sesskey();
    $job = $reportservice->load_job($jobid, (int)$USER->id);
    if (empty($job['preflight']['can_migrate'])) {
        throw new moodle_exception('invalidaccess', 'error');
    }

    core_php_time_limit::raise(60 * 60);
    raise_memory_limit(MEMORY_EXTRA);
    $categoryresult = (new \local_migrationtool\service\category_service())->restore($job['manifest']['categories']);
    $restoremanager = new \local_migrationtool\restore_manager();
    $results = [];
    $started = microtime(true);

    foreach ($job['manifest']['courses'] as $course) {
        $sourceid = (int)$course['source_id'];
        $sourcecategory = (int)$course['category_id'];
        $targetcategory = $categoryresult['map'][$sourcecategory] ?? 0;
        $itemstarted = microtime(true);
        try {
            if (!$targetcategory) {
                throw new moodle_exception('invalidcategoryid');
            }
            $restored = $restoremanager->restore_course(
                $job['package_dir'] . '/' . $course['backup_file'],
                $targetcategory,
                $job['manifest']['scope']
            );
            $results[] = array_merge([
                'source_course_id' => $sourceid,
                'fullname' => $course['fullname'],
                'status' => 'success',
            ], $restored);
        } catch (Throwable $e) {
            $results[] = [
                'source_course_id' => $sourceid,
                'fullname' => $course['fullname'],
                'status' => 'failed',
                'target_category_id' => $targetcategory,
                'duration' => round(microtime(true) - $itemstarted, 3),
                'error' => $e->getMessage(),
            ];
        }
    }

    $failed = count(array_filter($results, static fn(array $item): bool => $item['status'] !== 'success'));
    $report = [
        'type' => 'migration',
        'status' => $failed ? 'warning' : 'success',
        'created_at' => date('c'),
        'created_at_ts' => time(),
        'user_id' => (int)$USER->id,
        'source' => $job['manifest']['source'],
        'target' => $job['preflight']['target'],
        'scope' => $job['manifest']['scope'],
        'simulation_report_id' => $job['preflight']['report_id'] ?? null,
        'categories' => $categoryresult['items'],
        'courses' => $results,
        'summary' => [
            'total' => count($results),
            'success' => count($results) - $failed,
            'failed' => $failed,
            'duration' => round(microtime(true) - $started, 3),
        ],
    ];
    $reportid = $reportservice->save($report);
    \local_migrationtool\service\storage_service::remove_tree(\local_migrationtool\service\storage_service::jobs_dir() . '/' . $jobid);

    redirect(new moodle_url('/local/migrationtool/report_view.php', ['id' => $reportid]),
        get_string('migrationfinished', 'local_migrationtool'));
}

$mform = new \local_migrationtool\form\import_form();
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/migrationtool/index.php'));
}

$preflight = null;
if ($data = $mform->get_data()) {
    $jobid = \local_migrationtool\service\storage_service::new_id('import');
    $jobdir = \local_migrationtool\service\storage_service::create_job_dir($jobid);
    $zipfile = $jobdir . '/upload.zip';
    if (!$mform->save_file('migrationzip', $zipfile, true)) {
        throw new moodle_exception('invalidpackage', 'local_migrationtool');
    }

    $packagedir = $jobdir . '/package';
    try {
        $manifest = (new \local_migrationtool\service\package_service())->extract($zipfile, $packagedir);
        $preflight = (new \local_migrationtool\service\preflight_service())->analyse($manifest, $packagedir);
        $preflight['user_id'] = (int)$USER->id;
        $preflight['package_name'] = clean_filename($_FILES['migrationzip']['name'] ?? 'migration.zip');
        $reportid = $reportservice->save($preflight);
        $preflight['report_id'] = $reportid;
        $reportservice->save($preflight);
        $reportservice->save_job([
            'job_id' => $jobid,
            'user_id' => (int)$USER->id,
            'created_at' => date('c'),
            'zip_file' => $zipfile,
            'package_dir' => $packagedir,
            'manifest' => $manifest,
            'preflight' => $preflight,
        ]);
    } catch (Throwable $e) {
        \local_migrationtool\service\storage_service::remove_tree($jobdir);
        throw $e;
    }
}

echo $OUTPUT->header();
if ($preflight) {
    echo html_writer::tag('h3', get_string('preflighttitle', 'local_migrationtool'));
    $class = $preflight['status'] === 'error' ? 'notifyproblem' : ($preflight['status'] === 'warning' ? 'notifywarning' : 'notifysuccess');
    echo $OUTPUT->notification(strtoupper($preflight['status']), $class);

    if (!empty($preflight['errors'])) {
        echo html_writer::tag('h4', 'Błędy');
        echo html_writer::alist(array_map('s', $preflight['errors']));
    }
    if (!empty($preflight['warnings'])) {
        echo html_writer::tag('h4', 'Ostrzeżenia');
        echo html_writer::alist(array_map('s', $preflight['warnings']));
    }

    $reporturl = new moodle_url('/local/migrationtool/report_view.php', ['id' => $preflight['report_id']]);
    echo html_writer::div(html_writer::link($reporturl, get_string('reportdetails', 'local_migrationtool')), 'mb-3');
    if ($preflight['can_migrate']) {
        $executeurl = new moodle_url('/local/migrationtool/import.php', [
            'action' => 'execute',
            'jobid' => $jobid,
            'sesskey' => sesskey(),
        ]);
        echo $OUTPUT->single_button($executeurl, get_string('executeimport', 'local_migrationtool'), 'post', ['class' => 'btn-primary']);
    }
} else {
    echo $OUTPUT->notification(get_string('scopeinfo', 'local_migrationtool'), 'info');
    $mform->display();
}
echo $OUTPUT->footer();

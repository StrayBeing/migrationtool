<?php

require('../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/migrationtool:manage', $context);
require_once(__DIR__ . '/forms/export_form.php');

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/migrationtool/export.php'));
$PAGE->set_title(get_string('export', 'local_migrationtool'));
$PAGE->set_heading(get_string('export', 'local_migrationtool'));

$mform = new \local_migrationtool\form\export_form();
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/migrationtool/index.php'));
}

$output = '';
if ($data = $mform->get_data()) {
    core_php_time_limit::raise(60 * 60);
    raise_memory_limit(MEMORY_EXTRA);

    $courseids = array_values(array_unique(array_map('intval', (array)$data->courses)));
    if (empty($courseids)) {
        throw new invalid_parameter_exception('No courses selected.');
    }
    $scope = \local_migrationtool\service\scope_service::from_form($data);

    $jobid = \local_migrationtool\service\storage_service::new_id('export');
    $jobdir = \local_migrationtool\service\storage_service::create_job_dir($jobid);
    $coursedir = $jobdir . '/courses';
    make_writable_directory($coursedir);

    $manager = new \local_migrationtool\backup_manager();
    $backups = [];
    $started = microtime(true);
    foreach ($courseids as $courseid) {
        $backups[$courseid] = $manager->export_course($courseid, $coursedir, $scope);
    }

    $manifestservice = new \local_migrationtool\service\manifest_service();
    $manifest = $manifestservice->build($courseids, $backups, $scope);
    $manifestservice->save($jobdir . '/manifest.json', $manifest);

    $relativefiles = ['manifest.json'];
    foreach ($manifest['courses'] as $course) {
        $relativefiles[] = $course['backup_file'];
    }
    $filename = 'moodle_courses_' . date('Ymd_His') . '_' . substr($jobid, -8) . '.zip';
    $packagepath = \local_migrationtool\service\storage_service::packages_dir() . '/' . $filename;
    (new \local_migrationtool\service\zip_service())->create($packagepath, $jobdir, $relativefiles);
//report content
    $report = [
        'type' => 'export',
        'status' => 'success',
        'created_at' => date('c'),
        'created_at_ts' => time(),
        'user_id' => (int)$USER->id,
        'package_file' => $filename,
        'source' => $manifest['source'],
        'scope' => $manifest['scope'],
        'course_count' => count($manifest['courses']),
        'courses' => $manifest['courses'],
        'duration' => round(microtime(true) - $started, 3),
    ];
    $reportid = (new \local_migrationtool\service\report_service())->save($report);

    $downloadurl = new moodle_url('/local/migrationtool/download.php', [
        'type' => 'package',
        'file' => $filename,
        'sesskey' => sesskey(),
    ]);
    $reporturl = new moodle_url('/local/migrationtool/report_view.php', ['id' => $reportid]);
    $output .= $OUTPUT->notification(get_string('exportcreated', 'local_migrationtool'), 'success');
    $output .= html_writer::div(html_writer::link($downloadurl,
        get_string('downloadpackage', 'local_migrationtool')), 'mb-3');
    $output .= html_writer::div(html_writer::link($reporturl,
        get_string('reportdetails', 'local_migrationtool')), 'mb-3');

    \local_migrationtool\service\storage_service::remove_tree($jobdir);
}

echo $OUTPUT->header();
echo $output;
if (!$data) {
    echo $OUTPUT->notification(get_string('scopeinfo', 'local_migrationtool'), 'info');
    $mform->display();
}
echo $OUTPUT->footer();

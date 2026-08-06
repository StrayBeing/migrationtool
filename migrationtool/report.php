<?php

require('../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/migrationtool:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/migrationtool/report.php'));
$PAGE->set_title(get_string('reports', 'local_migrationtool'));
$PAGE->set_heading(get_string('reports', 'local_migrationtool'));

$reports = (new \local_migrationtool\service\report_service())->list();

echo $OUTPUT->header();
if (empty($reports)) {
    echo $OUTPUT->notification(get_string('noreports', 'local_migrationtool'), 'info');
} else {
    $table = new html_table();
    $table->head = ['Data', 'Typ', 'Status', 'Kursy', ''];
    foreach ($reports as $report) {
        $id = $report['report_id'] ?? basename($report['_path'], '.json');
        $count = $report['course_count'] ?? $report['summary']['total'] ?? count($report['courses'] ?? []);
        $url = new moodle_url('/local/migrationtool/report_view.php', ['id' => $id]);
        $table->data[] = [
            s($report['created_at'] ?? ''),
            s($report['type'] ?? ''),
            s($report['status'] ?? ''),
            (int)$count,
            html_writer::link($url, get_string('reportdetails', 'local_migrationtool')),
        ];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();

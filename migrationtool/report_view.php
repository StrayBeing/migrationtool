<?php

require('../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/migrationtool:manage', $context);
$id = required_param('id', PARAM_ALPHANUMEXT);
$report = (new \local_migrationtool\service\report_service())->load($id);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/migrationtool/report_view.php', ['id' => $id]));
$PAGE->set_title(get_string('reportdetails', 'local_migrationtool'));
$PAGE->set_heading(get_string('reportdetails', 'local_migrationtool'));

echo $OUTPUT->header();

echo html_writer::tag('h3', s(($report['type'] ?? 'report') . ' — ' . ($report['status'] ?? '')));
$summary = new html_table();
$summary->data = [
    ['ID', s($id)],
    ['Data', s($report['created_at'] ?? '')],
    ['Typ', s($report['type'] ?? '')],
    ['Status', s($report['status'] ?? '')],
    ['Moodle źródłowy', s(($report['source']['moodle_release'] ?? '') . ' (' . ($report['source']['moodle_version'] ?? '') . ')')],
    ['Moodle docelowy', s(($report['target']['moodle_release'] ?? '') . ' (' . ($report['target']['moodle_version'] ?? '') . ')')],
];
echo html_writer::table($summary);

if (!empty($report['errors'])) {
    echo html_writer::tag('h4', 'Błędy');
    echo html_writer::alist(array_map('s', $report['errors']));
}
if (!empty($report['warnings'])) {
    echo html_writer::tag('h4', 'Ostrzeżenia');
    echo html_writer::alist(array_map('s', $report['warnings']));
}

if (!empty($report['checks']['components'])) {
    echo html_writer::tag('h4', 'Kompatybilność komponentów');
    $table = new html_table();
    $table->head = ['Komponent', 'Status', 'Wersja źródłowa', 'Wersja docelowa', 'Informacja'];
    foreach ($report['checks']['components'] as $item) {
        $table->data[] = [
            s($item['component'] ?? ''),
            s($item['status'] ?? ''),
            s((string)($item['source_version'] ?? '')),
            s((string)($item['target_version'] ?? '')),
            s($item['message'] ?? ''),
        ];
    }
    echo html_writer::table($table);
}

$courseitems = $report['courses'] ?? $report['checks']['backups'] ?? [];
if (!empty($courseitems)) {
    echo html_writer::tag('h4', 'Kursy');
    $table = new html_table();
    $table->head = ['Kurs źródłowy', 'Nazwa', 'Status', 'Kurs docelowy', 'Kategoria docelowa', 'Czas', 'Błąd'];
    foreach ($courseitems as $item) {
        $table->data[] = [
            s((string)($item['source_course_id'] ?? $item['source_id'] ?? '')),
            s($item['fullname'] ?? ''),
            s($item['status'] ?? ''),
            s((string)($item['target_course_id'] ?? '')),
            s((string)($item['target_category_id'] ?? '')),
            s((string)($item['duration'] ?? '')),
            s($item['error'] ?? $item['message'] ?? ''),
        ];
    }
    echo html_writer::table($table);
}

$downloadurl = new moodle_url('/local/migrationtool/download.php', [
    'type' => 'report',
    'file' => $id . '.json',
    'sesskey' => sesskey(),
]);
echo html_writer::div(html_writer::link($downloadurl, get_string('downloadreport', 'local_migrationtool')), 'mt-3');
echo html_writer::div(html_writer::link(new moodle_url('/local/migrationtool/report.php'), get_string('back', 'local_migrationtool')), 'mt-3');

echo $OUTPUT->footer();

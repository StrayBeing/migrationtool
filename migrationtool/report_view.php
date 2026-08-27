<?php

require('../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/migrationtool:manage', $context);

$id = required_param('id', PARAM_ALPHANUMEXT);

$report = (new \local_migrationtool\service\report_service())->load($id);
$reporttype = $report['type'] ?? 'report';

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/migrationtool/report_view.php', ['id' => $id]));
$PAGE->set_title(get_string('reportdetails', 'local_migrationtool'));
$PAGE->set_heading(get_string('reportdetails', 'local_migrationtool'));

echo $OUTPUT->header();

echo html_writer::tag(
    'h3',
    s($reporttype . ' — ' . ($report['status'] ?? ''))
);

$summary = new html_table();

$summary->data = [
    ['ID', s($id)],
    ['Data', s($report['created_at'] ?? '')],
    ['Typ', s($reporttype)],
    ['Status', s($report['status'] ?? '')],
];

if (!empty($report['source'])) {
    $summary->data[] = [
        'Moodle źródłowy',
        s(
            ($report['source']['moodle_release'] ?? '')
            . ' ('
            . ($report['source']['moodle_version'] ?? '')
            . ')'
        ),
    ];
}

if (!empty($report['target'])) {
    $summary->data[] = [
        'Moodle docelowy',
        s(
            ($report['target']['moodle_release'] ?? '')
            . ' ('
            . ($report['target']['moodle_version'] ?? '')
            . ')'
        ),
    ];
}

if ($reporttype === 'export') {
    if (!empty($report['package_file'])) {
        $summary->data[] = [
            'Pakiet migracyjny',
            s($report['package_file']),
        ];
    }

    if (isset($report['course_count'])) {
        $summary->data[] = [
            'Liczba kursów',
            (int)$report['course_count'],
        ];
    }
}

if (isset($report['duration'])) {
    $summary->data[] = [
        'Czas wykonania',
        s((string)$report['duration']) . ' s',
    ];
} else if (isset($report['summary']['duration'])) {
    $summary->data[] = [
        'Czas wykonania',
        s((string)$report['summary']['duration']) . ' s',
    ];
}

echo html_writer::table($summary);

if (!empty($report['scope'])) {
    echo html_writer::tag(
        'h4',
        get_string('reportscope', 'local_migrationtool')
    );

    $scopetable = new html_table();

    $scopetable->head = [
        get_string('scopeelement', 'local_migrationtool'),
        get_string('scopeincluded', 'local_migrationtool'),
    ];

    $labels = [
        'course_structure' => 'scopestructure',
        'activities' => 'scopeactivities',
        'files' => 'scopefiles',
        'question_bank' => 'scopequestionbank',
        'blocks' => 'scopeblocks',
        'users' => 'scopeusers',
        'enrolments' => 'scopeenrolments',
        'grade_histories' => 'scopegradehistories',
    ];

    foreach ($labels as $key => $stringkey) {
        $scopetable->data[] = [
            get_string($stringkey, 'local_migrationtool'),
            !empty($report['scope'][$key])
                ? get_string('yes')
                : get_string('no'),
        ];
    }

    echo html_writer::table($scopetable);
}

if (!empty($report['errors'])) {
    echo html_writer::tag('h4', 'Błędy');

    echo html_writer::alist(
        array_map('s', $report['errors'])
    );
}

if (!empty($report['warnings'])) {
    echo html_writer::tag('h4', 'Ostrzeżenia');

    echo html_writer::alist(
        array_map('s', $report['warnings'])
    );
}

if (!empty($report['checks']['components'])) {
    echo html_writer::tag('h4', 'Kompatybilność komponentów');

    $table = new html_table();

    $table->head = [
        'Komponent',
        'Status',
        'Wersja źródłowa',
        'Wersja docelowa',
        'Informacja',
    ];

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
if ($reporttype === 'export' && !empty($report['courses'])) {

    echo html_writer::tag('h4', 'Kursy');

    $table = new html_table();

    $table->head = [
        'ID kursu',
        'Nazwa',
        'Plik kopii',
        'Rozmiar',
        'Czas eksportu',
    ];

    foreach ($report['courses'] as $item) {

        $size = '';

        if (isset($item['size'])) {
            $size = (string)$item['size'] . ' B';
        }

        $duration = '';

        if (isset($item['export_duration'])) {
            $duration = (string)$item['export_duration'] . ' s';
        }

        $table->data[] = [
            s((string)($item['source_id'] ?? '')),
            s($item['fullname'] ?? ''),
            s($item['backup_file'] ?? ''),
            s($size),
            s($duration),
        ];
    }

    echo html_writer::table($table);
}
if ($reporttype !== 'export') {

    $courseitems = $report['courses']
        ?? ($report['checks']['backups'] ?? []);

    if (!empty($courseitems)) {

        echo html_writer::tag('h4', 'Kursy');

        $table = new html_table();

        $table->head = [
            'Kurs źródłowy',
            'Nazwa',
            'Status',
            'Kurs docelowy',
            'Kategoria docelowa',
            'Czas',
            'Błąd',
        ];

        foreach ($courseitems as $item) {

            $duration = '';

            if (isset($item['duration'])) {
                $duration = (string)$item['duration'] . ' s';
            }

            $table->data[] = [
                s((string)(
                    $item['source_course_id']
                    ?? $item['source_id']
                    ?? ''
                )),
                s($item['fullname'] ?? ''),
                s($item['status'] ?? ''),
                s((string)($item['target_course_id'] ?? '')),
                s((string)($item['target_category_id'] ?? '')),
                s($duration),
                s($item['error'] ?? $item['message'] ?? ''),
            ];
        }

        echo html_writer::table($table);
    }
}

$downloadurl = new moodle_url(
    '/local/migrationtool/download.php',
    [
        'type' => 'report',
        'file' => $id . '.json',
        'sesskey' => sesskey(),
    ]
);

echo html_writer::div(
    html_writer::link(
        $downloadurl,
        get_string('downloadreport', 'local_migrationtool')
    ),
    'mt-3'
);

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/migrationtool/report.php'),
        get_string('back', 'local_migrationtool')
    ),
    'mt-3'
);

echo $OUTPUT->footer();

<?php
//main page change appearance
require('../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/migrationtool:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/migrationtool/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_migrationtool'));
$PAGE->set_heading(get_string('pluginname', 'local_migrationtool'));

$links = [
    new action_link(new moodle_url('/local/migrationtool/export.php'), get_string('export', 'local_migrationtool')),
    new action_link(new moodle_url('/local/migrationtool/import.php'), get_string('import', 'local_migrationtool')),
    new action_link(new moodle_url('/local/migrationtool/report.php'), get_string('reports', 'local_migrationtool')),
];

echo $OUTPUT->header();
echo $OUTPUT->notification(get_string('scopeinfo', 'local_migrationtool'), 'info');
echo html_writer::start_div('local-migrationtool-menu');
foreach ($links as $link) {
    echo html_writer::div($OUTPUT->render($link), 'mb-3');
}
echo html_writer::end_div();
echo $OUTPUT->footer();

<?php
require('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

global $CFG, $PAGE, $OUTPUT;

$file = required_param('file', PARAM_FILE);

$PAGE->set_url('/local/migrationtool/report_view.php');
$PAGE->set_title('Report view');
$PAGE->set_heading('Szczegóły raportu');

echo $OUTPUT->header();

$data = json_decode(file_get_contents(
    $CFG->dataroot.'/migrationtool/reports/'.$file
), true);

echo "<h3>📄 {$file}</h3>";

foreach ($data['courses'] as $c) {

    $color = $c['status'] === 'success' ? '#2ecc71' : '#e74c3c';

    echo "<div style='margin:5px;padding:8px;border-left:5px solid {$color};background:#fff'>
        <b>Course:</b> {$c['courseid']} <br>
        <b>Status:</b> {$c['status']}
    </div>";
}

echo $OUTPUT->footer();

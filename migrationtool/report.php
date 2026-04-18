<?php
require('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

global $CFG, $OUTPUT, $PAGE;

$PAGE->set_url('/local/migrationtool/report.php');
$PAGE->set_title('Migration Reports');
$PAGE->set_heading('Raporty migracji');

echo $OUTPUT->header();

$files = glob($CFG->dataroot.'/migrationtool/reports/*.json');

echo "<h3>Raporty</h3>";

echo "<div style='display:grid;gap:10px;'>";

foreach ($files as $file) {

    $data = json_decode(file_get_contents($file), true);

    $total = count($data['courses']);
    $ok = 0;
    $fail = 0;

    foreach ($data['courses'] as $c) {
        if ($c['status'] === 'success') $ok++;
        else $fail++;
    }

    $color = ($fail > 0) ? '#e74c3c' : '#2ecc71';

    $url = new moodle_url('/local/migrationtool/report_view.php', [
        'file' => basename($file)
    ]);

    echo "<a href='{$url}' style='padding:10px;border:1px solid #ccc;display:block;border-left:5px solid {$color}'>
        📦 ".basename($file)."<br>
        ✔ {$ok} | ❌ {$fail} | 📊 {$total}
    </a>";
}

echo "</div>";

echo $OUTPUT->footer();

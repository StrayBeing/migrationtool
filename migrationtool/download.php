<?php

require('../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$filename = required_param('file', PARAM_FILE);

$path = $CFG->dataroot.'/migrationtool/backups/'.$filename;

if (!file_exists($path)) {
    print_error('File not found');
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.filesize($path));

readfile($path);
exit;

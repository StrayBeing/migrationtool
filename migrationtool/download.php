<?php

require('../../config.php');
require_once($CFG->libdir . '/filelib.php');

require_login();
require_sesskey();
require_capability('local/migrationtool:manage', context_system::instance());

$type = required_param('type', PARAM_ALPHA);
$filename = required_param('file', PARAM_FILE);
//for repeated use for reports or zip
if ($type === 'package') {
    $base = \local_migrationtool\service\storage_service::packages_dir();
    $mimetype = 'application/zip';
} else if ($type === 'report') {
    $base = \local_migrationtool\service\storage_service::reports_dir();
    $mimetype = 'application/json';
} else {
    throw new invalid_parameter_exception('Invalid download type.');
}

$path = $base . '/' . $filename;
if (!is_readable($path) || dirname(realpath($path)) !== realpath($base)) {
    throw new moodle_exception('filenotfound', 'error');
}

send_file($path, $filename, 0, 0, false, true, $mimetype);

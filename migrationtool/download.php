<?php

require('../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

require_once($CFG->libdir.'/filelib.php');

$filename = required_param('file', PARAM_FILE);

$path = $CFG->dataroot.'/migrationtool/backups/'.$filename;

if (!file_exists($path)) {
    throw new moodle_exception('filenotfound', 'error');
}

send_file($path, $filename);

<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('tools', new admin_externalpage(
        'local_migrationtool',
        get_string('pluginname', 'local_migrationtool'),
        new moodle_url('/local/migrationtool/index.php'),
        'local/migrationtool:manage'
    ));
}

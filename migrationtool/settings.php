<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $ADMIN->add('tools',
        new admin_externalpage(
            'migrationtool',
            get_string('pluginname', 'local_migrationtool'),
            new moodle_url('/local/migrationtool/index.php')
        )
    );

}

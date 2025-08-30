<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_digisign_settings', get_string('pluginname', 'local_digisign'));

    $settings->add(new admin_setting_configtext(
        'local_digisign/api_key',
        get_string('api_key', 'local_digisign'),
        get_string('api_key_desc', 'local_digisign'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_digisign/api_url',
        get_string('api_url', 'local_digisign'),
        get_string('api_url_desc', 'local_digisign'),
        'https://api.docuseal.com',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_digisign/store_local_copy',
        get_string('store_local_copy', 'local_digisign'),
        get_string('store_local_copy_desc', 'local_digisign'),
        1
    ));

    $ADMIN->add('localplugins', $settings);
}
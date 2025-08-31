<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_digisign', get_string('pluginname', 'local_digisign'));
    $ADMIN->add('localplugins', $settings);

    // API Configuration
    $settings->add(new admin_setting_heading('local_digisign/api_configuration', 
        get_string('api_configuration', 'local_digisign'), 
        get_string('api_configuration_desc', 'local_digisign')));

    $settings->add(new admin_setting_configtext('local_digisign/api_url', 
        get_string('api_url', 'local_digisign'), 
        get_string('api_url_desc', 'local_digisign'), 
        'https://sign.operatortraining.academy/api', 
        PARAM_URL));

    $settings->add(new admin_setting_configtext('local_digisign/api_key', 
        get_string('api_key', 'local_digisign'), 
        get_string('api_key_desc', 'local_digisign'), 
        '', 
        PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_digisign/timeout', 
        get_string('timeout', 'local_digisign'), 
        get_string('timeout_desc', 'local_digisign'), 
        '30', 
        PARAM_INT));
}
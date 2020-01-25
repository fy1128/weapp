<?php

// Settings
$settings = [];

// Path settings
$settings['root'] = dirname(__DIR__);
$settings['temp'] = $settings['root'] . '/tmp';
$settings['public'] = $settings['root'] . '/public';

// Error Handling Middleware settings
$settings['error_handler_middleware'] = [

    // Should be set to false in production
    'display_error_details' => true,

    // Parameter is passed to the default ErrorHandler
    // View in rendered output by enabling the "displayErrorDetails" setting.
    // For the console and unit tests we also disable it
    'log_errors' => true,

    // Display error details in error log
    'log_error_details' => true,
];

# wechat wework settings
$settings['we_work'] = [
    "corp_id" => "tAp1uR7w8T8L1yaeLT",
    "apps" => [
        1000003 => [
            "name" => "appname",
            "agentid" => 1000003,
            "secret" => "i-rqQmNEYsU3kx2bvZRD4q20OQUnRwkhqiTs7G-xO3c"
        ],
        1000002 => [
            "name" => "appname",
            "agentid" => 1000002,
            "secret" => "i-rqQmNEYsU3kx2bvZRD4q20OQUnRwkhqiTs7G-xO3c"
        ],
        // 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
    ],
    "department" => ["secret" => "i-rqQmNEYsU3kx2bvZRD4q20OQUnRwkhqiTs7G-xO3c"],
    'response_type' => 'array',
    'log' => [
        'level' => 'debug',
        'file' => __DIR__.'/wechat.log',
    ]
];

$config = getenv('WEAPP_CONFIG');
if (!empty($config)) {
    if ($config = json_decode($config, true)) {
        return array_merge($settings, $config);
    }
}

return $settings;
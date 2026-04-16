<?php
return [
    'app_host'         => env('APP_HOST', ''),
    'app_debug'        => env('APP_DEBUG', false),
    'app_trace'        => false,
    'default_timezone' => env('APP.DEFAULT_TIMEZONE', 'America/New_York'),
    'default_app'      => 'index',
    'app_map'          => [],
    'domain_bind'      => [],
    'deny_app_list'    => [],
    'exception_handle' => '',
    'show_error_msg'   => true,
];

<?php
return [
    'default'         => env('DATABASE.DRIVER', 'mysql'),
    'connections'     => [
        'mysql' => [
            'type'            => env('DATABASE.TYPE', 'mysql'),
            'hostname'        => env('DATABASE.HOSTNAME', '127.0.0.1'),
            'database'        => env('DATABASE.DATABASE', 'precision_portal'),
            'username'        => env('DATABASE.USERNAME', 'root'),
            'password'        => env('DATABASE.PASSWORD', ''),
            'hostport'        => env('DATABASE.HOSTPORT', '3306'),
            'charset'         => env('DATABASE.CHARSET', 'utf8mb4'),
            'prefix'          => env('DATABASE.PREFIX', 'pp_'),
            'deploy'          => 0,
            'rw_separate'     => false,
            'master_num'      => 1,
            'slave_no'        => '',
            'fields_strict'   => true,
            'break_reconnect' => false,
            'trigger_sql'     => env('DATABASE.DEBUG', false),
            'fields_cache'    => false,
        ],
    ],
];

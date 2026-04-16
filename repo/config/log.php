<?php
return [
    'default'      => env('LOG.CHANNEL', 'file'),
    'channels'     => [
        'file' => [
            'type'           => 'File',
            'path'           => '',
            'single'         => false,
            'apart_level'    => [],
            'max_files'      => 30,
            'json'           => false,
            'format'         => '[%s][%s] %s',
            'realtime_write' => false,
        ],
    ],
];

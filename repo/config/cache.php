<?php
return [
    'default' => env('CACHE.DRIVER', 'file'),
    'stores'  => [
        'file' => [
            'type'       => 'File',
            'path'       => '',
            'tag_prefix' => 'tag:',
            'serialize'  => [],
            'expire'     => 0,
        ],
    ],
];

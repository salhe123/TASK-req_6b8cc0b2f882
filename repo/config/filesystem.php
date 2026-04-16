<?php
return [
    'default' => env('FILESYSTEM.DRIVER', 'local'),
    'disks'   => [
        'local' => [
            'type' => 'local',
            'root' => app()->getRuntimePath() . 'storage',
        ],
        'uploads' => [
            'type'       => 'local',
            'root'       => app()->getRootPath() . 'storage/uploads',
            'visibility' => 'public',
            'url'        => '/storage/uploads',
        ],
    ],
];

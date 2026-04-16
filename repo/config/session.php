<?php
return [
    'id'             => '',
    'type'           => env('SESSION.TYPE', 'file'),
    'store'          => null,
    'expire'         => (int) env('SESSION.EXPIRE', 900),
    'var_session_id' => '',
    'name'           => 'PHPSESSID',
    'prefix'         => 'pp_',
    'serialize'      => ['serialize', 'unserialize'],
];

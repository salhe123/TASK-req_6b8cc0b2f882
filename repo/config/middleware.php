<?php
return [
    'alias' => [
        'auth'     => app\middleware\AuthMiddleware::class,
        'rbac'     => app\middleware\RbacMiddleware::class,
        'throttle' => app\middleware\ThrottleMiddleware::class,
        'stepup'   => app\middleware\StepUpMiddleware::class,
    ],
    'priority' => [],
];

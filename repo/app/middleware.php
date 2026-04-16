<?php
// Global middleware — runs on every app request.
// ThrottleMiddleware is a no-op for unauthenticated requests, so it is safe to list globally.
return [
    \think\middleware\SessionInit::class,
    \app\middleware\ThrottleMiddleware::class,
];

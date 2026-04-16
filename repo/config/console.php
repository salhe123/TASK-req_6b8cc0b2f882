<?php
return [
    'commands' => [
        \app\command\ExpireAppointments::class,
        \app\command\RiskScore::class,
        \app\command\AuditArchive::class,
    ],
];

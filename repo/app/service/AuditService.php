<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;
use think\facade\Request;

class AuditService
{
    /**
     * Log an operation to the append-only audit log.
     */
    public static function log(
        string $action,
        string $entityType,
        ?int   $entityId = null,
        ?array $beforeData = null,
        ?array $afterData = null,
        ?int   $userId = null
    ): void {
        if ($userId === null) {
            $user = session('user');
            $userId = $user['id'] ?? null;
        }

        Db::name('audit_logs')->insert([
            'user_id'     => $userId,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'before_data' => $beforeData ? json_encode($beforeData) : null,
            'after_data'  => $afterData ? json_encode($afterData) : null,
            'ip_address'  => Request::ip(),
            'user_agent'  => Request::header('user-agent', ''),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }
}

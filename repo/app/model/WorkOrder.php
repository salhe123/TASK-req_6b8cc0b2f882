<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class WorkOrder extends Model
{
    protected $table = 'pp_work_orders';
    protected $autoWriteTimestamp = false;

    protected $type = [
        'id'                 => 'integer',
        'mps_id'             => 'integer',
        'work_center_id'     => 'integer',
        'product_id'         => 'integer',
        'quantity_planned'   => 'integer',
        'quantity_completed' => 'integer',
        'quantity_rework'    => 'integer',
        'downtime_minutes'   => 'integer',
    ];

    // Strict state machine: PENDING → IN_PROGRESS → COMPLETED.
    // A work order may not jump directly from PENDING to COMPLETED —
    // operators must explicitly start the order before reporting
    // completion, so the IN_PROGRESS audit row always exists for traceability.
    const TRANSITIONS = [
        'PENDING'     => ['IN_PROGRESS'],
        'IN_PROGRESS' => ['COMPLETED'],
        'COMPLETED'   => [],
    ];

    public function canTransitionTo(string $newStatus): bool
    {
        $allowed = self::TRANSITIONS[$this->status] ?? [];
        return in_array($newStatus, $allowed, true);
    }
}

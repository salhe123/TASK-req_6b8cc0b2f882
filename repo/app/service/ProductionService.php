<?php
declare(strict_types=1);

namespace app\service;

use app\model\MpsPlan;
use app\model\WorkOrder;
use app\model\WorkCenter;
use think\exception\ValidateException;
use think\facade\Db;

class ProductionService
{
    /**
     * Create an MPS plan entry.
     */
    public static function createMps(array $data, int $createdBy): MpsPlan
    {
        $now = date('Y-m-d H:i:s');

        // Validate work center exists
        $wc = WorkCenter::findOrFail($data['workCenterId'] ?? ($data['work_center_id'] ?? 0));

        $mps = new MpsPlan();
        $mps->product_id     = $data['productId'];
        $mps->work_center_id = $wc->id;
        $mps->week_start     = self::parseDate($data['weekStart']);
        $mps->quantity        = (int) $data['quantity'];
        $mps->planned_hours  = $data['plannedHours'] ?? self::estimateHours((int) $data['quantity']);
        $mps->status         = 'ACTIVE';
        $mps->created_by     = $createdBy;
        $mps->created_at     = $now;
        $mps->updated_at     = $now;
        $mps->save();

        AuditService::log('MPS_CREATED', 'mps_plan', $mps->id, null, $mps->toArray());

        return $mps;
    }

    /**
     * Update an MPS plan entry.
     */
    public static function updateMps(int $id, array $data): MpsPlan
    {
        $mps = MpsPlan::findOrFail($id);
        $before = $mps->toArray();

        if (isset($data['quantity'])) {
            $mps->quantity = (int) $data['quantity'];
        }
        if (isset($data['weekStart'])) {
            $mps->week_start = self::parseDate($data['weekStart']);
        }
        if (isset($data['plannedHours'])) {
            $mps->planned_hours = $data['plannedHours'];
        }
        if (isset($data['status'])) {
            $mps->status = $data['status'];
        }

        $mps->updated_at = date('Y-m-d H:i:s');
        $mps->save();

        AuditService::log('MPS_UPDATED', 'mps_plan', $id, $before, $mps->toArray());

        return $mps;
    }

    /**
     * Delete an MPS plan entry.
     */
    public static function deleteMps(int $id): void
    {
        $mps = MpsPlan::findOrFail($id);
        $before = $mps->toArray();

        // Check for existing work orders
        $woCount = WorkOrder::where('mps_id', $id)->count();
        if ($woCount > 0) {
            throw new ValidateException('Cannot delete MPS with existing work orders');
        }

        $mps->delete();

        AuditService::log('MPS_DELETED', 'mps_plan', $id, $before, null);
    }

    /**
     * Explode MPS demand into work orders (MRP).
     */
    public static function explode(int $mpsId): int
    {
        $mps = MpsPlan::findOrFail($mpsId);
        $now = date('Y-m-d H:i:s');

        // Simple explosion: create work orders based on quantity
        // In a real MRP, this would consider BOM, lead times, etc.
        $batchSize  = 100; // units per work order
        $remaining  = $mps->quantity;
        $created    = 0;

        Db::startTrans();
        try {
            while ($remaining > 0) {
                $qty = min($batchSize, $remaining);

                $wo = new WorkOrder();
                $wo->mps_id             = $mps->id;
                $wo->work_center_id     = $mps->work_center_id;
                $wo->product_id         = $mps->product_id;
                $wo->quantity_planned   = $qty;
                $wo->quantity_completed = 0;
                $wo->quantity_rework    = 0;
                $wo->downtime_minutes   = 0;
                $wo->status             = 'PENDING';
                $wo->created_at         = $now;
                $wo->updated_at         = $now;
                $wo->save();

                $remaining -= $qty;
                $created++;
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }

        AuditService::log('MPS_EXPLODED', 'mps_plan', $mpsId, null, [
            'work_orders_created' => $created,
        ]);

        return $created;
    }

    /**
     * Complete a work order with operator report. Transition-guarded: a COMPLETED
     * order cannot be completed again, and transitions are recorded in the
     * append-only `pp_work_order_history` table.
     */
    public static function completeWorkOrder(int $id, array $data, ?int $actorId = null): WorkOrder
    {
        $wo     = WorkOrder::findOrFail($id);
        $before = $wo->toArray();

        if (!$wo->canTransitionTo('COMPLETED')) {
            throw new \think\exception\HttpException(409, "Cannot transition work order from {$wo->status} to COMPLETED");
        }

        $validReasonCodes = [
            'MATERIAL_DELAY', 'MACHINE_BREAKDOWN', 'QUALITY_ISSUE',
            'OPERATOR_ERROR', 'TOOL_WEAR', 'SETUP_TIME', 'OTHER',
        ];

        if (!empty($data['reasonCode']) && !in_array($data['reasonCode'], $validReasonCodes, true)) {
            throw new ValidateException('Invalid reason code');
        }

        $oldStatus = $wo->status;

        $wo->quantity_completed = (int) ($data['quantityCompleted'] ?? 0);
        $wo->quantity_rework    = (int) ($data['quantityRework'] ?? 0);
        $wo->downtime_minutes   = (int) ($data['downtimeMinutes'] ?? 0);
        $wo->reason_code        = $data['reasonCode'] ?? null;
        $wo->status             = 'COMPLETED';
        $wo->completed_at       = date('Y-m-d H:i:s');
        $wo->updated_at         = date('Y-m-d H:i:s');
        $wo->save();

        self::recordWorkOrderHistory(
            $wo->id,
            $oldStatus,
            'COMPLETED',
            $actorId ?? ((session('user')['id'] ?? 1)),
            'Completed',
            [
                'quantityCompleted' => $wo->quantity_completed,
                'quantityRework'    => $wo->quantity_rework,
                'downtimeMinutes'   => $wo->downtime_minutes,
                'reasonCode'        => $wo->reason_code,
            ]
        );

        AuditService::log('WORK_ORDER_COMPLETED', 'work_order', $id, $before, $wo->toArray());

        return $wo;
    }

    /**
     * Admin-only work-order repair: force a state change with full audit trail.
     * Wrapped in a transaction so the state change and the history row commit
     * together (or both roll back).
     */
    public static function repairWorkOrder(int $id, string $targetState, int $adminId, string $reason): WorkOrder
    {
        $valid = ['PENDING', 'IN_PROGRESS', 'COMPLETED'];
        if (!in_array($targetState, $valid, true)) {
            throw new ValidateException('Invalid target state: ' . $targetState);
        }
        if (trim($reason) === '') {
            throw new ValidateException('Repair reason is required');
        }

        Db::startTrans();
        try {
            $wo = WorkOrder::findOrFail($id);
            $before = $wo->toArray();
            $oldStatus = $wo->status;

            $wo->status     = $targetState;
            $wo->updated_at = date('Y-m-d H:i:s');
            if ($targetState === 'COMPLETED' && empty($wo->completed_at)) {
                $wo->completed_at = date('Y-m-d H:i:s');
            } elseif ($targetState !== 'COMPLETED') {
                $wo->completed_at = null;
            }
            $wo->save();

            self::recordWorkOrderHistory(
                $wo->id,
                $oldStatus,
                $targetState,
                $adminId,
                '[REPAIR] ' . $reason,
                ['repair' => true, 'admin_id' => $adminId, 'before_state' => $oldStatus]
            );

            AuditService::log('WORK_ORDER_REPAIRED', 'work_order', $id, $before, $wo->toArray());
            Db::commit();
            return $wo;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * Start a pending work order (PENDING → IN_PROGRESS) with history record.
     */
    public static function startWorkOrder(int $id, ?int $actorId = null): WorkOrder
    {
        $wo = WorkOrder::findOrFail($id);
        if (!$wo->canTransitionTo('IN_PROGRESS')) {
            throw new \think\exception\HttpException(409, "Cannot start work order in state {$wo->status}");
        }
        $before = $wo->toArray();
        $oldStatus = $wo->status;
        $wo->status     = 'IN_PROGRESS';
        $wo->updated_at = date('Y-m-d H:i:s');
        $wo->save();

        self::recordWorkOrderHistory(
            $wo->id,
            $oldStatus,
            'IN_PROGRESS',
            $actorId ?? ((session('user')['id'] ?? 1)),
            'Started'
        );

        AuditService::log('WORK_ORDER_STARTED', 'work_order', $id, $before, $wo->toArray());
        return $wo;
    }

    private static function recordWorkOrderHistory(int $workOrderId, ?string $from, string $to, int $changedBy, ?string $reason = null, ?array $metadata = null): void
    {
        Db::name('work_order_history')->insert([
            'work_order_id' => $workOrderId,
            'from_status'   => $from,
            'to_status'     => $to,
            'changed_by'    => $changedBy,
            'reason'        => $reason,
            'metadata'      => $metadata ? json_encode($metadata) : null,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Calculate capacity loading per work center.
     */
    public static function getCapacityLoading(?int $workCenterId = null, ?string $weekStart = null): array
    {
        $query = WorkCenter::where('status', 'ACTIVE');
        if ($workCenterId) {
            $query->where('id', $workCenterId);
        }

        $workCenters = $query->select();
        $result = [];

        foreach ($workCenters as $wc) {
            $mpsQuery = MpsPlan::where('work_center_id', $wc->id)
                ->where('status', 'ACTIVE');

            if ($weekStart) {
                $mpsQuery->where('week_start', self::parseDate($weekStart));
            } else {
                // Default: current rolling 12-week window
                $now = date('Y-m-d');
                $end = date('Y-m-d', strtotime('+12 weeks'));
                $mpsQuery->where('week_start', '>=', $now)
                         ->where('week_start', '<=', $end);
            }

            $plannedHours  = (float) $mpsQuery->sum('planned_hours');
            $capacityHours = (float) $wc->capacity_hours;
            $loadPercent   = $capacityHours > 0 ? round(($plannedHours / $capacityHours) * 100, 1) : 0;

            $result[] = [
                'workCenterId'  => $wc->id,
                'workCenterName' => $wc->name,
                'plannedHours'  => $plannedHours,
                'capacityHours' => $capacityHours,
                'loadPercent'   => $loadPercent,
                'warning'       => $loadPercent >= 90,
            ];
        }

        return $result;
    }

    private static function parseDate(string $input): string
    {
        $parsed = \DateTime::createFromFormat('m/d/Y', $input);
        if (!$parsed) {
            $parsed = new \DateTime($input);
        }
        return $parsed->format('Y-m-d');
    }

    private static function estimateHours(int $quantity): float
    {
        // Simple estimate: 0.1 hours per unit
        return round($quantity * 0.1, 2);
    }
}

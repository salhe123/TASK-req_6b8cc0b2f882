<?php
declare(strict_types=1);

namespace app\service;

use app\model\Product;
use think\exception\ValidateException;
use think\facade\Db;

class ModerationService
{
    /**
     * Get pending moderation items.
     */
    public static function getPending(string $type = '', int $page = 1, int $size = 20): array
    {
        $items = [];

        // Pending products
        if (!$type || $type === 'PRODUCT') {
            $products = Product::where('status', 'SUBMITTED')
                ->order('updated_at', 'desc')
                ->select()
                ->toArray();

            foreach ($products as $p) {
                $items[] = [
                    'type'      => 'PRODUCT',
                    'id'        => $p['id'],
                    'name'      => $p['name'],
                    'category'  => $p['category'],
                    'status'    => $p['status'],
                    'scores'    => [
                        'completeness' => $p['completeness_score'],
                        'consistency'  => $p['consistency_score'],
                    ],
                    'submitted_at' => $p['updated_at'],
                ];
            }
        }

        // Pending merge decisions — the schema's explicit `status='PENDING'` is
        // the source of truth; `action='REVIEW'` marks queue entries created
        // by the catalog dedup pipeline.
        if (!$type || $type === 'MERGE') {
            $merges = Db::name('moderation_decisions')
                ->where('item_type', 'MERGE')
                ->where('status', 'PENDING')
                ->order('created_at', 'desc')
                ->select()
                ->toArray();

            foreach ($merges as $m) {
                $items[] = [
                    'type'    => 'MERGE',
                    'id'      => $m['id'],
                    'item_id' => $m['item_id'],
                    'details' => json_decode($m['before_snapshot'], true),
                    'notes'   => $m['notes'],
                    'created_at' => $m['created_at'],
                ];
            }
        }

        $total = count($items);
        $items = array_slice($items, ($page - 1) * $size, $size);

        return ['list' => $items, 'total' => $total, 'page' => $page, 'size' => $size];
    }

    /**
     * Bulk approve or reject products.
     */
    public static function bulkAction(array $ids, string $action, int $moderatorId): int
    {
        if (!in_array($action, ['APPROVE', 'REJECT'], true)) {
            throw new ValidateException('Action must be APPROVE or REJECT');
        }

        $newStatus = $action === 'APPROVE' ? 'APPROVED' : 'REJECTED';
        $count = 0;

        foreach ($ids as $id) {
            $product = Product::find($id);
            if (!$product || $product->status !== 'SUBMITTED') {
                continue;
            }

            $before = $product->toArray();
            $product->status     = $newStatus;
            $product->updated_at = date('Y-m-d H:i:s');
            $product->save();

            Db::name('moderation_decisions')->insert([
                'item_type'       => 'PRODUCT',
                'item_id'         => $id,
                'action'          => $action,
                'moderator_id'    => $moderatorId,
                'before_snapshot' => json_encode($before),
                'after_snapshot'  => json_encode($product->toArray()),
                'notes'           => null,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);

            AuditService::log('PRODUCT_' . $action . 'D', 'product', $id, $before, $product->toArray());
            $count++;
        }

        return $count;
    }

    /**
     * Merge review: approve merge, reject, or mark as distinct.
     */
    public static function mergeReview(int $productIdA, int $productIdB, string $action, ?int $keepId, int $moderatorId): array
    {
        if (!in_array($action, ['MERGE', 'REJECT', 'DISTINCT'], true)) {
            throw new ValidateException('Action must be MERGE, REJECT, or DISTINCT');
        }

        $productA = Product::findOrFail($productIdA);
        $productB = Product::findOrFail($productIdB);

        $beforeA = $productA->toArray();
        $beforeB = $productB->toArray();
        $result  = [];

        if ($action === 'MERGE') {
            if (!$keepId || !in_array($keepId, [$productIdA, $productIdB])) {
                throw new ValidateException('keepId must be one of the two product IDs');
            }

            $removeId = ($keepId === $productIdA) ? $productIdB : $productIdA;
            $remove   = Product::find($removeId);

            $remove->status     = 'REJECTED';
            $remove->updated_at = date('Y-m-d H:i:s');
            $remove->save();

            $result = ['kept' => $keepId, 'removed' => $removeId];
        }

        $now = date('Y-m-d H:i:s');

        // Close out any PENDING merge-queue entries for this pair so the
        // moderator queue doesn't keep showing a stale row. Both orderings
        // (A→B and B→A) are swept because the dedup pipeline can record
        // either depending on which product submitted first.
        Db::name('moderation_decisions')
            ->where('item_type', 'MERGE')
            ->where('status', 'PENDING')
            ->where('item_id', 'IN', [$productIdA, $productIdB])
            ->update([
                'status'       => 'RESOLVED',
                'moderator_id' => $moderatorId,
                'resolved_at'  => $now,
            ]);

        Db::name('moderation_decisions')->insert([
            'item_type'       => 'MERGE',
            'item_id'         => $productIdA,
            'action'          => $action,
            'status'          => 'RESOLVED',
            'moderator_id'    => $moderatorId,
            'before_snapshot' => json_encode(['productA' => $beforeA, 'productB' => $beforeB]),
            'after_snapshot'  => json_encode($result),
            'notes'           => "Merge review: {$action}",
            'created_at'      => $now,
            'resolved_at'     => $now,
        ]);

        AuditService::log('MERGE_' . $action, 'product', $productIdA, [
            'productA' => $beforeA,
            'productB' => $beforeB,
        ], $result);

        return $result;
    }
}

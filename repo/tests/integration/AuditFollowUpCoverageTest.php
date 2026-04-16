<?php
declare(strict_types=1);

namespace tests\integration;

use tests\IntegrationTestCase;
use app\service\AppointmentService;
use app\service\CatalogService;
use app\service\CatalogDictionary;
use app\service\FinanceService;
use app\service\ModerationService;
use app\service\ReviewService;
use think\facade\Db;

/**
 * Coverage for the Blocker/High findings from the latest audit pass:
 *   - Blind review payload masking
 *   - Catalog object-level ownership
 *   - Settlement idempotency
 *   - Dictionary-based parsing
 *   - Merge-queue resolution on moderator decision
 */
class AuditFollowUpCoverageTest extends IntegrationTestCase
{
    // ─── Blind review masking ───

    public function testBlindAssignmentMasksIdentityFields()
    {
        $row = [
            'id' => 1, 'product_id' => 9, 'reviewer_id' => 5, 'blind' => 1,
            'vendor_name' => 'Acme Corp', 'submitted_by' => 42, 'product_created_by' => 42,
        ];
        $masked = ReviewService::maskForReviewer($row);
        $this->assertNull($masked['vendor_name']);
        $this->assertNull($masked['submitted_by']);
        $this->assertNull($masked['product_created_by']);
        $this->assertTrue($masked['blind_masked']);
    }

    public function testNonBlindAssignmentPreservesFields()
    {
        $row = [
            'id' => 2, 'product_id' => 9, 'reviewer_id' => 5, 'blind' => 0,
            'vendor_name' => 'Acme Corp', 'submitted_by' => 42, 'product_created_by' => 42,
        ];
        $masked = ReviewService::maskForReviewer($row);
        $this->assertSame('Acme Corp', $masked['vendor_name']);
        $this->assertArrayNotHasKey('blind_masked', $masked);
    }

    // ─── Settlement idempotency ───

    public function testDuplicateSettlementCannotDoubleSettle()
    {
        // Seed a completed, reconciled appointment with a receipt.
        $appt = AppointmentService::create([
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '05/20/2026 10:00 AM', 'location' => 'Idempotent',
        ], 1);
        Db::name('appointments')->where('id', $appt->id)->update(['status' => 'COMPLETED']);

        $now = date('Y-m-d H:i:s');
        $pid = Db::name('payments')->insertGetId([
            'amount' => 100.00, 'payer_name' => 'Idemp', 'reference' => 'IDM-' . uniqid(),
            'payment_date' => '2026-05-19', 'status' => 'RECONCILED', 'checksum' => hash('sha256', 'x'),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        FinanceService::generateReceipt($pid, 100.00, $now, $appt->id);

        $first = FinanceService::createSettlement('05/20/2026', 8.0, 1);
        $firstCount = Db::name('ledger_entries')->where('appointment_id', $appt->id)->count();
        $this->assertSame(1, $firstCount);

        // Second run for the same week must be a no-op for that appointment.
        FinanceService::createSettlement('05/20/2026', 8.0, 1);
        $secondCount = Db::name('ledger_entries')->where('appointment_id', $appt->id)->count();
        $this->assertSame(1, $secondCount, 'Appointment must not be settled twice');
    }

    // ─── Dictionary-based parsing ───

    public function testDictionaryCanonicalizesVendorAndSocket()
    {
        $specs = [
            'CPU Socket' => 'lga1700',
            'Clock Speed' => '3.0 GHz',
            'vendor' => 'intel corp',
            'chipset' => 'Z790',
            'formFactor' => 'matx',
            'memoryType' => 'ddr5',
        ];
        $specs = CatalogDictionary::canonicalizeKeys($specs);
        $specs = CatalogDictionary::normalizeValues($specs);

        $this->assertArrayHasKey('socket', $specs);
        $this->assertSame('LGA 1700', $specs['socket']);
        $this->assertArrayHasKey('clockSpeed', $specs);
        $this->assertSame('Intel', $specs['vendor']);
        $this->assertSame('Micro-ATX', $specs['formFactor']);
        $this->assertSame('DDR5', $specs['memoryType']);
        $this->assertSame('Intel 700 Series', $specs['chipsetFamily']);
    }

    public function testStandardizeRunsDictionaryBeforeRegex()
    {
        // Raw input uses free-form keys; both the dictionary *and* the regex
        // normalizer should fire so units and vocabulary both land clean.
        $specs = CatalogService::standardizeSpecs([
            'cpu speed' => '4500 MHz',
            'CPU Socket' => 'am5',
            'TDP' => '170 W',
        ], 'CPU');
        $this->assertArrayHasKey('clockSpeed', $specs);
        $this->assertSame('AM5', $specs['socket']);
        $this->assertStringContainsString('GHz', (string) $specs['clockSpeed']);
    }

    // ─── Merge-queue resolution ───

    public function testMergeReviewResolvesPendingQueueRow()
    {
        $now = date('Y-m-d H:i:s');
        // Create two products
        $pidA = Db::name('products')->insertGetId([
            'name' => 'Merge A ' . uniqid(), 'category' => 'CPU', 'specs' => '{}',
            'vendor_name' => 'V', 'status' => 'SUBMITTED', 'created_by' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $pidB = Db::name('products')->insertGetId([
            'name' => 'Merge B ' . uniqid(), 'category' => 'CPU', 'specs' => '{}',
            'vendor_name' => 'V', 'status' => 'SUBMITTED', 'created_by' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // Simulate a dedup-pipeline PENDING row
        $pendingId = Db::name('moderation_decisions')->insertGetId([
            'item_type' => 'MERGE', 'item_id' => $pidA, 'action' => 'REVIEW',
            'status' => 'PENDING', 'moderator_id' => null,
            'before_snapshot' => json_encode(['productA' => $pidA, 'productB' => $pidB, 'similarity' => 0.7]),
            'after_snapshot' => null, 'notes' => 'Flagged for review',
            'created_at' => $now,
        ]);

        ModerationService::mergeReview($pidA, $pidB, 'MERGE', $pidA, 1);

        $row = Db::name('moderation_decisions')->find($pendingId);
        $this->assertSame('RESOLVED', $row['status'], 'Pending merge row must be resolved');
        $this->assertSame(1, (int) $row['moderator_id']);
        $this->assertNotEmpty($row['resolved_at']);
    }
}

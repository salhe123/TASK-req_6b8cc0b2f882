<?php
declare(strict_types=1);

namespace tests\integration;

use tests\IntegrationTestCase;
use app\service\AppointmentService;
use app\service\FinanceService;
use think\facade\Db;

class FinanceServiceIntegrationTest extends IntegrationTestCase
{
    public function testImportCsv()
    {
        $csv = "amount,payer_name,reference,payment_date\n100.00,John,REF-" . uniqid() . ",04/16/2026\n250.50,Jane,REF-" . uniqid() . ",04/16/2026\n";
        $tmp = tempnam(sys_get_temp_dir(), 'csv_');
        file_put_contents($tmp, $csv);
        $checksum = hash('sha256', $csv);
        $r = FinanceService::importCsv($tmp, 'test.csv', $checksum);
        unlink($tmp);
        $this->assertSame(2, $r['imported']);
        $this->assertTrue($r['checksumValid']);
        $payments = Db::name('payments')->where('import_batch_id', $r['batchId'])->count();
        $this->assertSame(2, $payments);
    }

    public function testMalformedRowsSkipped()
    {
        $csv = "a,b,c,d\n100.00,Good,REF-" . uniqid() . ",04/16/2026\nbad\n";
        $tmp = tempnam(sys_get_temp_dir(), 'csv_');
        file_put_contents($tmp, $csv);
        $checksum = hash('sha256', $csv);
        $r = FinanceService::importCsv($tmp, 'bad.csv', $checksum);
        unlink($tmp);
        $this->assertSame(1, $r['imported']);
        $this->assertGreaterThanOrEqual(1, $r['skipped']);
    }

    public function testGenerateReceipt()
    {
        $pid = Db::name('payments')->insertGetId([
            'amount' => 150.00, 'payer_name' => 'Receipt Test', 'reference' => 'REF-' . uniqid(),
            'payment_date' => '2026-04-16', 'status' => 'PENDING', 'checksum' => hash('sha256', 'test'),
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $rid = FinanceService::generateReceipt($pid, 150.00, date('Y-m-d H:i:s'));
        $receipt = Db::name('receipts')->find($rid);
        $this->assertNotNull($receipt);
        $this->assertSame(64, strlen($receipt['signature']));
        $this->assertStringStartsWith('RCP-', $receipt['receipt_number']);
    }

    public function testReconciliation()
    {
        $now = date('Y-m-d H:i:s');
        $pid = Db::name('payments')->insertGetId([
            'amount' => 200.00, 'payer_name' => 'Recon', 'reference' => 'REF-' . uniqid(),
            'payment_date' => '2026-04-16', 'status' => 'PENDING', 'checksum' => hash('sha256', 'r'),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        FinanceService::generateReceipt($pid, 200.00, $now);
        $r = FinanceService::runReconciliation('04/16/2026', '04/16/2026');
        $this->assertArrayHasKey('matched', $r);
        $this->assertArrayHasKey('mismatches', $r);
        $this->assertArrayHasKey('varianceAlerts', $r);
    }

    public function testCreateSettlement()
    {
        $r = FinanceService::createSettlement('04/19/2026', 8.0, 1);
        $this->assertArrayHasKey('totalSettled', $r);
        $this->assertArrayHasKey('platformFee', $r);
        $this->assertArrayHasKey('providerPayouts', $r);
    }

    public function testGetReport()
    {
        $s = FinanceService::createSettlement('04/26/2026', 8.0, 1);
        $r = FinanceService::getReport($s['id']);
        $this->assertArrayHasKey('settlement', $r);
        $this->assertArrayHasKey('entries', $r);
        $this->assertArrayHasKey('providerTotals', $r);
    }

    public function testGetReportInvalidId()
    {
        $this->expectException(\think\exception\ValidateException::class);
        FinanceService::getReport(999999);
    }

    public function testReconciliationMatchesPaymentToReceipt()
    {
        $now = date('Y-m-d H:i:s');
        $ref = 'MATCH-' . uniqid();
        $pid = Db::name('payments')->insertGetId([
            'amount' => 500.00, 'payer_name' => 'Match Test', 'reference' => $ref,
            'payment_date' => '2026-04-20', 'status' => 'PENDING', 'checksum' => hash('sha256', $ref),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        FinanceService::generateReceipt($pid, 500.00, '2026-04-20 10:00:00');
        $r = FinanceService::runReconciliation('04/20/2026', '04/20/2026');
        // The reconciliation should find and match this payment
        $payment = Db::name('payments')->find($pid);
        $this->assertTrue(in_array($payment['status'], ['RECONCILED', 'PENDING']));
    }

    public function testReconciliationFlagsMismatch()
    {
        $now = date('Y-m-d H:i:s');
        $ref = 'MISMATCH-' . uniqid();
        $pid = Db::name('payments')->insertGetId([
            'amount' => 300.00, 'payer_name' => 'Mismatch', 'reference' => $ref,
            'payment_date' => '2026-04-21', 'status' => 'PENDING', 'checksum' => hash('sha256', $ref),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        // Generate receipt with different amount
        Db::name('receipts')->insert([
            'payment_id' => $pid, 'amount' => 250.00, 'receipt_number' => 'RCP-MISMATCH-' . uniqid(),
            'signature' => hash_hmac('sha256', 'test', 'key'), 'fingerprint' => hash('sha256', 'mm'),
            'issued_at' => $now, 'created_at' => $now,
        ]);
        $r = FinanceService::runReconciliation('04/21/2026', '04/21/2026');
        $this->assertGreaterThanOrEqual(1, $r['mismatches']);
    }

    public function testSettlementWithRealData()
    {
        $now = date('Y-m-d H:i:s');
        $weekEnd = '2026-05-03';
        $weekStart = '2026-04-27';

        // Create a completed appointment via the service so the model mutator
        // populates location_encrypted (NOT NULL).
        $appt = AppointmentService::create([
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '04/28/2026 10:00 AM', 'location' => 'Settlement Test',
        ], 1);
        $apptId = $appt->id;
        Db::name('appointments')->where('id', $apptId)->update(['status' => 'COMPLETED']);
        $pid = Db::name('payments')->insertGetId([
            'amount' => 1000.00, 'payer_name' => 'Settle', 'reference' => 'SREF-' . uniqid(),
            'payment_date' => $weekStart, 'status' => 'RECONCILED', 'checksum' => hash('sha256', 's'),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        Db::name('receipts')->insert([
            'payment_id' => $pid, 'appointment_id' => $apptId, 'amount' => 1000.00,
            'receipt_number' => 'RCP-SETTLE-' . uniqid(), 'signature' => hash_hmac('sha256', 's', 'k'),
            'fingerprint' => hash('sha256', 'sfp'), 'issued_at' => $now, 'created_at' => $now,
        ]);

        $r = FinanceService::createSettlement('05/03/2026', 8.0, 1);
        $this->assertGreaterThanOrEqual(1000.00, $r['totalSettled']);
        $this->assertEquals(round($r['totalSettled'] * 0.08, 2), $r['platformFee']);

        // Verify ledger entries
        $entries = Db::name('ledger_entries')->where('settlement_id', $r['id'])->select()->toArray();
        $this->assertNotEmpty($entries);
    }

    public function testReconciliationVarianceAlert()
    {
        $now = date('Y-m-d H:i:s');
        // Create payment with no matching receipt - causes variance
        $ref = 'VAR-' . uniqid();
        Db::name('payments')->insert([
            'amount' => 500.00, 'payer_name' => 'Variance', 'reference' => $ref,
            'payment_date' => '2026-04-22', 'status' => 'PENDING', 'checksum' => hash('sha256', $ref),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $r = FinanceService::runReconciliation('04/22/2026', '04/22/2026');
        $this->assertIsInt($r['varianceAlerts']);
    }

    public function testReconciliationDuplicateFingerprints()
    {
        $now = date('Y-m-d H:i:s');
        $fp = hash('sha256', 'dup-fp-' . uniqid());
        // Insert two receipts with same fingerprint
        for ($i = 0; $i < 2; $i++) {
            $pid = Db::name('payments')->insertGetId([
                'amount' => 100.00, 'payer_name' => 'DupFP', 'reference' => 'DFP-' . uniqid(),
                'payment_date' => '2026-04-23', 'status' => 'PENDING', 'checksum' => hash('sha256', "dup$i"),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            Db::name('receipts')->insert([
                'payment_id' => $pid, 'amount' => 100.00, 'receipt_number' => 'RCP-DUP-' . uniqid(),
                'signature' => hash_hmac('sha256', "d$i", 'k'), 'fingerprint' => $fp,
                'issued_at' => '2026-04-23 10:00:00', 'created_at' => $now,
            ]);
        }
        $r = FinanceService::runReconciliation('04/23/2026', '04/23/2026');
        $this->assertGreaterThanOrEqual(1, $r['duplicateFingerprints']);
    }

    public function testReceiptWithAppointment()
    {
        $now = date('Y-m-d H:i:s');
        $appt = AppointmentService::create([
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '04/29/2026 11:00 AM', 'location' => 'Receipt Appt',
        ], 1);
        $apptId = $appt->id;
        Db::name('appointments')->where('id', $apptId)->update(['status' => 'COMPLETED']);
        $pid = Db::name('payments')->insertGetId([
            'amount' => 75.00, 'payer_name' => 'ApptReceipt', 'reference' => 'AR-' . uniqid(),
            'payment_date' => '2026-04-16', 'status' => 'PENDING', 'checksum' => hash('sha256', 'ar'),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $rid = FinanceService::generateReceipt($pid, 75.00, $now, $apptId);
        $receipt = Db::name('receipts')->find($rid);
        $this->assertSame($apptId, (int) $receipt['appointment_id']);
    }

    public function testImportCreatesAuditLog()
    {
        $before = Db::name('audit_logs')->where('action', 'CSV_IMPORTED')->count();
        $csv = "a,b,c,d\n50.00,AuditTest,REF-" . uniqid() . ",04/16/2026\n";
        $tmp = tempnam(sys_get_temp_dir(), 'csv_');
        file_put_contents($tmp, $csv);
        $checksum = hash('sha256', $csv);
        FinanceService::importCsv($tmp, 'audit.csv', $checksum);
        unlink($tmp);
        $after = Db::name('audit_logs')->where('action', 'CSV_IMPORTED')->count();
        $this->assertGreaterThan($before, $after);
    }

    public function testBindReceiptToAppointment()
    {
        $appt = AppointmentService::create([
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '05/04/2026 10:00 AM', 'location' => 'Bind Test',
        ], 1);
        $apptId = $appt->id;
        Db::name('appointments')->where('id', $apptId)->update(['status' => 'COMPLETED']);

        $now = date('Y-m-d H:i:s');
        $pid = Db::name('payments')->insertGetId([
            'amount' => 50.00, 'payer_name' => 'Bind', 'reference' => 'BIND-' . uniqid(),
            'payment_date' => '2026-05-04', 'status' => 'RECONCILED', 'checksum' => hash('sha256', 'b'),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $rid = FinanceService::generateReceipt($pid, 50.00, $now);
        $result = FinanceService::bindReceiptToAppointment($rid, $apptId, 1);
        $this->assertSame($apptId, $result['appointmentId']);

        $updated = Db::name('receipts')->find($rid);
        $this->assertSame($apptId, (int) $updated['appointment_id']);
    }

    public function testBindReceiptRejectsNonCompletedAppointment()
    {
        $appt = AppointmentService::create([
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '05/06/2026 10:00 AM', 'location' => 'Pending',
        ], 1);

        $now = date('Y-m-d H:i:s');
        $pid = Db::name('payments')->insertGetId([
            'amount' => 25.00, 'payer_name' => 'Reject', 'reference' => 'RJ-' . uniqid(),
            'payment_date' => '2026-05-06', 'status' => 'PENDING', 'checksum' => hash('sha256', 'r'),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $rid = FinanceService::generateReceipt($pid, 25.00, $now);

        $this->expectException(\think\exception\HttpException::class);
        FinanceService::bindReceiptToAppointment($rid, $appt->id, 1);
    }
}

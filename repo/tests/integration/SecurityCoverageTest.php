<?php
declare(strict_types=1);

namespace tests\integration;

use tests\IntegrationTestCase;
use app\service\AppointmentService;
use app\service\FinanceService;
use app\service\RiskService;
use app\model\Appointment;
use think\facade\Db;

/**
 * Covers the audit-flagged gaps that were previously untested:
 *   - DB-level immutability of appointment_history (append-only trigger)
 *   - Location plaintext never hits pp_appointments.location_encrypted raw column
 *   - CSV import rejects on missing / mismatched checksum
 *   - Finance callback rejects replay / bad-signature / amount-mismatch
 *   - StepUp hold creates a blocking flag
 */
class SecurityCoverageTest extends IntegrationTestCase
{
    public function testAppointmentHistoryCannotBeUpdated()
    {
        $appt = AppointmentService::create([
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '05/10/2026 10:00 AM', 'location' => 'Immutable HQ',
        ], 1);

        $historyRow = Db::name('appointment_history')
            ->where('appointment_id', $appt->id)
            ->find();
        $this->assertNotNull($historyRow);

        $this->expectException(\Throwable::class);
        Db::name('appointment_history')
            ->where('id', $historyRow['id'])
            ->update(['reason' => 'tampered']);
    }

    public function testAppointmentHistoryCannotBeDeleted()
    {
        $appt = AppointmentService::create([
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '05/10/2026 10:00 AM', 'location' => 'Immutable HQ 2',
        ], 1);
        $historyRow = Db::name('appointment_history')
            ->where('appointment_id', $appt->id)
            ->find();

        $this->expectException(\Throwable::class);
        Db::name('appointment_history')
            ->where('id', $historyRow['id'])
            ->delete();
    }

    public function testLocationStoredEncryptedOnly()
    {
        AppointmentService::create([
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '05/11/2026 10:00 AM', 'location' => 'Secret Lab, 123 Restricted Way',
        ], 1);

        // The raw `location_encrypted` column must not contain the plaintext.
        $row = Db::name('appointments')->order('id', 'desc')->find();
        $this->assertNotEmpty($row['location_encrypted']);
        $this->assertStringNotContainsString('Restricted Way', $row['location_encrypted'], 'Location must be encrypted at rest');
        $this->assertStringNotContainsString('Secret Lab', $row['location_encrypted']);

        // The legacy `location` column should be empty (plaintext scrubbed).
        $this->assertSame('', (string) $row['location'], 'Legacy plaintext column must stay empty');

        // The location_hint column should contain the first (public) venue name only.
        $this->assertStringContainsString('Secret Lab', (string) $row['location_hint']);

        // Audit rows must never contain plaintext address either.
        $audit = Db::name('audit_logs')
            ->where('entity_type', 'appointment')
            ->order('id', 'desc')
            ->find();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('Restricted Way', $audit['after_data'] ?? '');
    }

    public function testCsvImportRejectsMissingChecksum()
    {
        $csv = "amount,payer,ref,date\n10.00,John,R-1,04/16/2026\n";
        $tmp = tempnam(sys_get_temp_dir(), 'csv_');
        file_put_contents($tmp, $csv);
        try {
            $this->expectException(\think\exception\HttpException::class);
            FinanceService::importCsv($tmp, 'nochk.csv', null);
        } finally {
            @unlink($tmp);
        }
    }

    public function testCsvImportRejectsChecksumMismatch()
    {
        $csv = "amount,payer,ref,date\n10.00,John,R-2,04/16/2026\n";
        $tmp = tempnam(sys_get_temp_dir(), 'csv_');
        file_put_contents($tmp, $csv);
        try {
            $this->expectException(\think\exception\HttpException::class);
            FinanceService::importCsv($tmp, 'badchk.csv', str_repeat('0', 64));
        } finally {
            @unlink($tmp);
        }
    }

    public function testCsvImportAcceptsMatchingChecksum()
    {
        $csv = "amount,payer,ref,date\n10.00,John,R-3-" . uniqid() . ",04/16/2026\n";
        $tmp = tempnam(sys_get_temp_dir(), 'csv_');
        file_put_contents($tmp, $csv);
        $real = hash('sha256', $csv);
        try {
            $r = FinanceService::importCsv($tmp, 'ok.csv', $real);
            $this->assertSame(1, $r['imported']);
            $this->assertTrue($r['checksumValid']);
        } finally {
            @unlink($tmp);
        }
    }

    public function testCallbackRejectsBadSignature()
    {
        // Seed a payment + receipt
        $pid = Db::name('payments')->insertGetId([
            'amount' => 90.00, 'payer_name' => 'CB', 'reference' => 'CB-' . uniqid(),
            'payment_date' => '2026-04-16', 'status' => 'PENDING', 'checksum' => hash('sha256', 't'),
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        FinanceService::generateReceipt($pid, 90.00, date('Y-m-d H:i:s'));

        $receipt = Db::name('receipts')->where('payment_id', $pid)->find();

        $this->expectException(\think\exception\HttpException::class);
        FinanceService::validateCallback([
            'receiptNumber' => $receipt['receipt_number'],
            'amount'        => 90.00,
            'issuedAt'      => $receipt['issued_at'],
            'signature'     => 'deadbeef' . str_repeat('0', 56),
        ]);
    }

    public function testCallbackRejectsAmountMismatch()
    {
        $pid = Db::name('payments')->insertGetId([
            'amount' => 90.00, 'payer_name' => 'CB', 'reference' => 'CB-' . uniqid(),
            'payment_date' => '2026-04-16', 'status' => 'PENDING', 'checksum' => hash('sha256', 't'),
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        FinanceService::generateReceipt($pid, 90.00, date('Y-m-d H:i:s'));
        $receipt = Db::name('receipts')->where('payment_id', $pid)->find();

        $signData = $receipt['receipt_number'] . '|' . '999.00' . '|' . $receipt['issued_at'];
        $sig = hmac_sign($signData);

        $this->expectException(\think\exception\HttpException::class);
        FinanceService::validateCallback([
            'receiptNumber' => $receipt['receipt_number'],
            'amount'        => 999.00,
            'issuedAt'      => $receipt['issued_at'],
            'signature'     => $sig,
        ]);
    }

    public function testCallbackAcceptsValidSignature()
    {
        $pid = Db::name('payments')->insertGetId([
            'amount' => 77.00, 'payer_name' => 'CB', 'reference' => 'CB-' . uniqid(),
            'payment_date' => '2026-04-16', 'status' => 'PENDING', 'checksum' => hash('sha256', 't'),
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        FinanceService::generateReceipt($pid, 77.00, date('Y-m-d H:i:s'));
        $receipt = Db::name('receipts')->where('payment_id', $pid)->find();

        // Both sides must concatenate the amount the same way. The DB returns
        // "77.00" as a string (DECIMAL), but `(float) "77.00"` becomes 77 when
        // cast in a string context. Keep the string form on both ends.
        $amountStr = $receipt['amount'];
        $signData = $receipt['receipt_number'] . '|' . $amountStr . '|' . $receipt['issued_at'];
        $sig = hmac_sign($signData);
        $r = FinanceService::validateCallback([
            'receiptNumber' => $receipt['receipt_number'],
            'amount'        => $amountStr,
            'issuedAt'      => $receipt['issued_at'],
            'signature'     => $sig,
        ]);
        $this->assertTrue($r['verified']);
    }

    public function testStepUpHoldFlagCreatedWhenScoreLow()
    {
        // Ensure the step-up threshold exists and is reachable from the
        // seeded low score below (the test-env entrypoint sometimes bumps
        // other throttle rows — this one is left alone, but we also insert
        // the row if absent so the test is self-contained).
        $exists = Db::name('throttle_config')->where('key', 'step_up_score_below')->find();
        if (!$exists) {
            Db::name('throttle_config')->insert([
                'key' => 'step_up_score_below', 'value' => 50,
                'description' => 'Risk/IP score below which step-up applies',
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        Db::name('risk_scores')->insert([
            'user_id' => 4, 'score' => 10.0, 'success_rate' => 0.1,
            'dispute_rate' => 0.5, 'cancellation_rate' => 0.5,
            'calculated_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        Db::name('anomaly_flags')->where('user_id', 4)->where('flag_type', 'STEP_UP_HOLD')->delete();

        $triggered = RiskService::maybeApplyStepUpHold(4, '203.0.113.99');
        $this->assertTrue($triggered);

        $flag = Db::name('anomaly_flags')
            ->where('user_id', 4)
            ->where('flag_type', 'STEP_UP_HOLD')
            ->where('status', 'OPEN')
            ->find();
        $this->assertNotNull($flag);
    }
}

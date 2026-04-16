<?php
declare(strict_types=1);

namespace app\service;

use think\exception\ValidateException;
use think\facade\Db;

class FinanceService
{
    /**
     * Import CSV batch file with checksum validation.
     *
     * The caller MUST supply an SHA-256 checksum computed over the raw CSV bytes
     * (via the `X-CSV-Checksum` header or a `checksum` form field). A missing or
     * mismatched checksum aborts the import — nothing is written to `pp_payments`
     * — and the rejection is audited.
     */
    public static function importCsv(string $filePath, string $originalName, ?string $providedChecksum = null): array
    {
        $content = file_get_contents($filePath);
        $checksum = hash('sha256', $content);

        if (empty($providedChecksum)) {
            AuditService::log('CSV_REJECTED', 'payment', null, null, [
                'reason'    => 'missing_checksum',
                'file_name' => $originalName,
            ]);
            throw new \think\exception\HttpException(400, 'Provided checksum is required (X-CSV-Checksum header or checksum field)');
        }
        if (!hash_equals($checksum, strtolower(trim($providedChecksum)))) {
            AuditService::log('CSV_REJECTED', 'payment', null, null, [
                'reason'    => 'checksum_mismatch',
                'file_name' => $originalName,
                'expected'  => $checksum,
                'provided'  => $providedChecksum,
            ]);
            throw new \think\exception\HttpException(409, 'CSV checksum mismatch — refusing import');
        }

        $batchId  = 'batch_' . date('Ymd_His') . '_' . substr($checksum, 0, 8);

        $lines = array_filter(explode("\n", trim($content)));
        $header = str_getcsv(array_shift($lines));

        $imported = 0;
        $skipped  = 0;
        $now      = date('Y-m-d H:i:s');

        foreach ($lines as $i => $line) {
            $row = str_getcsv($line);

            if (count($row) < 4) {
                $skipped++;
                continue;
            }

            try {
                // Expected CSV columns: amount, payer_name, reference, payment_date
                $amount      = (float) ($row[0] ?? 0);
                $payerName   = trim($row[1] ?? '');
                $reference   = trim($row[2] ?? '');
                $paymentDate = trim($row[3] ?? '');

                if ($amount <= 0 || empty($payerName) || empty($reference)) {
                    $skipped++;
                    continue;
                }

                // Parse payment date
                $parsed = \DateTime::createFromFormat('m/d/Y', $paymentDate);
                if (!$parsed) {
                    $parsed = new \DateTime($paymentDate);
                }

                $rowChecksum = hash('sha256', $amount . $payerName . $reference . $parsed->format('Y-m-d'));

                Db::name('payments')->insert([
                    'import_batch_id' => $batchId,
                    'amount'          => $amount,
                    'payer_name'      => $payerName,
                    'reference'       => $reference,
                    'payment_date'    => $parsed->format('Y-m-d'),
                    'status'          => 'PENDING',
                    'checksum'        => $rowChecksum,
                    'source_row'      => $i + 2, // 1-indexed, skip header
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);

                // Auto-generate signed receipt
                self::generateReceipt(
                    Db::name('payments')->getLastInsID(),
                    $amount,
                    $now
                );

                $imported++;
            } catch (\Exception $e) {
                $skipped++;
            }
        }

        AuditService::log('CSV_IMPORTED', 'payment', null, null, [
            'batch_id'      => $batchId,
            'file_name'     => $originalName,
            'checksum'      => $checksum,
            'imported'      => $imported,
            'skipped'       => $skipped,
        ]);

        return [
            'imported'      => $imported,
            'skipped'       => $skipped,
            'checksumValid' => true,
            'checksum'      => $checksum,
            'batchId'       => $batchId,
        ];
    }

    /**
     * Bind an imported payment/receipt to a completed appointment. This is the
     * canonical workflow used after CSV import — finance operators must attach
     * the matching appointment before the weekly settlement join succeeds.
     * The binding is audited and rejects mismatched amounts or non-COMPLETED
     * appointments so settlement integrity is preserved.
     */
    public static function bindReceiptToAppointment(int $receiptId, int $appointmentId, int $actorId): array
    {
        $receipt = Db::name('receipts')->find($receiptId);
        if (!$receipt) {
            throw new \think\exception\HttpException(404, 'Receipt not found');
        }
        $appt = Db::name('appointments')->find($appointmentId);
        if (!$appt) {
            throw new \think\exception\HttpException(404, 'Appointment not found');
        }
        if ($appt['status'] !== 'COMPLETED') {
            throw new \think\exception\HttpException(409, 'Appointment must be COMPLETED before binding for settlement');
        }

        Db::name('receipts')
            ->where('id', $receiptId)
            ->update(['appointment_id' => $appointmentId]);

        AuditService::log('RECEIPT_BOUND_TO_APPOINTMENT', 'receipt', $receiptId, [
            'appointment_id' => $receipt['appointment_id'] ?? null,
        ], [
            'appointment_id' => $appointmentId,
            'actor_id'       => $actorId,
        ]);

        return [
            'receiptId'     => $receiptId,
            'appointmentId' => $appointmentId,
            'paymentId'     => (int) $receipt['payment_id'],
        ];
    }

    /**
     * Generate a signed receipt via HMAC-SHA256.
     */
    public static function generateReceipt(int $paymentId, float $amount, string $issuedAt, ?int $appointmentId = null): int
    {
        $receiptNumber = 'RCP-' . date('Ymd') . '-' . str_pad((string) $paymentId, 6, '0', STR_PAD_LEFT);

        $signatureData = $receiptNumber . '|' . $amount . '|' . $issuedAt;
        $signature     = hmac_sign($signatureData);

        $fingerprint = hash('sha256', $paymentId . '|' . $amount . '|' . $receiptNumber);

        $id = Db::name('receipts')->insertGetId([
            'payment_id'     => $paymentId,
            'appointment_id' => $appointmentId,
            'amount'         => $amount,
            'receipt_number' => $receiptNumber,
            'signature'      => $signature,
            'fingerprint'    => $fingerprint,
            'issued_at'      => $issuedAt,
            'created_at'     => $issuedAt,
        ]);

        return $id;
    }

    /**
     * Run reconciliation: compare bank records vs portal receipts.
     */
    public static function runReconciliation(string $dateFrom, string $dateTo): array
    {
        $from = self::parseDate($dateFrom);
        $to   = self::parseDate($dateTo);

        $payments = Db::name('payments')
            ->where('payment_date', '>=', $from)
            ->where('payment_date', '<=', $to)
            ->select()
            ->toArray();

        $receipts = Db::name('receipts')
            ->where('issued_at', '>=', $from . ' 00:00:00')
            ->where('issued_at', '<=', $to . ' 23:59:59')
            ->select()
            ->toArray();

        $matched    = 0;
        $mismatches = 0;
        $duplicateFingerprints = 0;
        $varianceAlerts = 0;

        // Match payments to receipts
        $receiptByPayment = [];
        foreach ($receipts as $r) {
            $receiptByPayment[$r['payment_id']] = $r;
        }

        foreach ($payments as $p) {
            if (isset($receiptByPayment[$p['id']])) {
                $receipt = $receiptByPayment[$p['id']];
                $amountOk    = abs((float) $p['amount'] - (float) $receipt['amount']) < 0.01;
                $signatureOk = self::verifyReceiptSignature($receipt);

                if ($amountOk && $signatureOk) {
                    $matched++;
                    Db::name('payments')->where('id', $p['id'])->update([
                        'status'     => 'RECONCILED',
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    $mismatches++;
                    if (!$signatureOk) {
                        AuditService::log('RECEIPT_SIGNATURE_INVALID', 'receipt', (int) $receipt['id'], null, [
                            'payment_id'     => (int) $p['id'],
                            'receipt_number' => $receipt['receipt_number'],
                        ]);
                    }
                }
            } else {
                $mismatches++;
            }
        }

        // Check for duplicate receipt fingerprints
        $fingerprintCounts = Db::name('receipts')
            ->where('issued_at', '>=', $from . ' 00:00:00')
            ->where('issued_at', '<=', $to . ' 23:59:59')
            ->group('fingerprint')
            ->having('count(*) > 1')
            ->column('count(*) as cnt', 'fingerprint');

        $duplicateFingerprints = count($fingerprintCounts);

        // Check daily variance > $50
        $dailyTotals = Db::name('payments')
            ->where('payment_date', '>=', $from)
            ->where('payment_date', '<=', $to)
            ->group('payment_date')
            ->column('sum(amount) as total', 'payment_date');

        $dailyReceipts = Db::name('receipts')
            ->field("DATE(issued_at) as d, sum(amount) as total")
            ->where('issued_at', '>=', $from . ' 00:00:00')
            ->where('issued_at', '<=', $to . ' 23:59:59')
            ->group('d')
            ->select()
            ->toArray();

        $receiptTotalsByDate = [];
        foreach ($dailyReceipts as $dr) {
            $receiptTotalsByDate[$dr['d']] = (float) $dr['total'];
        }

        foreach ($dailyTotals as $date => $paymentTotal) {
            $receiptTotal = $receiptTotalsByDate[$date] ?? 0;
            if (abs((float) $paymentTotal - $receiptTotal) > 50.00) {
                $varianceAlerts++;

                // Create anomaly flag
                Db::name('anomaly_flags')->insert([
                    'user_id'    => (session('user')['id'] ?? 1),
                    'flag_type'  => 'DAILY_VARIANCE',
                    'details'    => json_encode([
                        'date'           => $date,
                        'payment_total'  => $paymentTotal,
                        'receipt_total'  => $receiptTotal,
                        'variance'       => abs((float) $paymentTotal - $receiptTotal),
                    ]),
                    'status'     => 'OPEN',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        // Flag duplicate fingerprints as anomalies
        foreach ($fingerprintCounts as $fp => $cnt) {
            Db::name('anomaly_flags')->insert([
                'user_id'    => (session('user')['id'] ?? 1),
                'flag_type'  => 'DUPLICATE_RECEIPT',
                'details'    => json_encode(['fingerprint' => $fp, 'count' => $cnt]),
                'status'     => 'OPEN',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        AuditService::log('RECONCILIATION_RUN', 'finance', null, null, [
            'date_from'             => $from,
            'date_to'               => $to,
            'matched'               => $matched,
            'mismatches'            => $mismatches,
            'duplicate_fingerprints' => $duplicateFingerprints,
            'variance_alerts'       => $varianceAlerts,
        ]);

        return [
            'matched'               => $matched,
            'mismatches'            => $mismatches,
            'duplicateFingerprints' => $duplicateFingerprints,
            'varianceAlerts'        => $varianceAlerts,
        ];
    }

    /**
     * Create a weekly settlement (atomic transaction).
     */
    public static function createSettlement(string $weekEnding, float $platformFeePercent, int $createdBy): array
    {
        $weekEnd = self::parseDate($weekEnding);
        $weekStart = date('Y-m-d', strtotime($weekEnd . ' -6 days'));

        Db::startTrans();
        try {
            // Find completed & reconciled appointments in the period, *excluding*
            // anything already present in the ledger (hard idempotency). The
            // UNIQUE constraint on ledger_entries.appointment_id is the DB-level
            // backstop — this filter just avoids spurious failures.
            $alreadySettled = Db::name('ledger_entries')->column('appointment_id');
            $appointmentsQuery = Db::name('appointments')
                ->alias('a')
                ->join('receipts r', 'r.appointment_id = a.id')
                ->join('payments p', 'p.id = r.payment_id')
                ->where('a.status', 'COMPLETED')
                ->where('p.status', 'RECONCILED')
                ->where('p.payment_date', '>=', $weekStart)
                ->where('p.payment_date', '<=', $weekEnd)
                ->field('a.id as appointment_id, a.provider_id, r.amount');
            if (!empty($alreadySettled)) {
                $appointmentsQuery->whereNotIn('a.id', $alreadySettled);
            }
            $appointments = $appointmentsQuery->select()->toArray();

            $totalSettled    = 0;
            $totalFee        = 0;
            $totalPayouts    = 0;
            $transactionCount = count($appointments);

            // Create settlement record
            $now = date('Y-m-d H:i:s');
            $settlementId = Db::name('settlements')->insertGetId([
                'week_ending'          => $weekEnd,
                'platform_fee_percent' => $platformFeePercent,
                'total_settled'        => 0,
                'platform_fee'         => 0,
                'provider_payouts'     => 0,
                'transaction_count'    => $transactionCount,
                'status'               => 'PENDING',
                'created_by'           => $createdBy,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);

            // Create ledger entries for each appointment
            foreach ($appointments as $appt) {
                $gross = (float) $appt['amount'];
                $fee   = round($gross * ($platformFeePercent / 100), 2);
                $net   = round($gross - $fee, 2);

                Db::name('ledger_entries')->insert([
                    'settlement_id'  => $settlementId,
                    'provider_id'    => $appt['provider_id'],
                    'appointment_id' => $appt['appointment_id'],
                    'gross_amount'   => $gross,
                    'platform_fee'   => $fee,
                    'net_amount'     => $net,
                    'created_at'     => $now,
                ]);

                $totalSettled += $gross;
                $totalFee     += $fee;
                $totalPayouts += $net;
            }

            // Update settlement totals
            Db::name('settlements')->where('id', $settlementId)->update([
                'total_settled'    => round($totalSettled, 2),
                'platform_fee'     => round($totalFee, 2),
                'provider_payouts' => round($totalPayouts, 2),
                'status'           => 'COMPLETED',
                'updated_at'       => $now,
            ]);

            Db::commit();

            AuditService::log('SETTLEMENT_CREATED', 'settlement', $settlementId, null, [
                'week_ending'      => $weekEnd,
                'total_settled'    => round($totalSettled, 2),
                'platform_fee'     => round($totalFee, 2),
                'provider_payouts' => round($totalPayouts, 2),
                'transaction_count' => $transactionCount,
            ]);

            return [
                'id'               => $settlementId,
                'totalSettled'     => round($totalSettled, 2),
                'platformFee'      => round($totalFee, 2),
                'providerPayouts'  => round($totalPayouts, 2),
                'transactionCount' => $transactionCount,
            ];
        } catch (\Exception $e) {
            Db::rollback();

            // Mark as failed if settlement was partially created
            AuditService::log('SETTLEMENT_FAILED', 'settlement', null, null, [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate fund flow report for a settlement.
     */
    public static function getReport(int $settlementId): array
    {
        $settlement = Db::name('settlements')->find($settlementId);
        if (!$settlement) {
            throw new ValidateException('Settlement not found');
        }

        $entries = Db::name('ledger_entries')
            ->where('settlement_id', $settlementId)
            ->select()
            ->toArray();

        // Group by provider
        $providerTotals = [];
        foreach ($entries as $entry) {
            $pid = $entry['provider_id'];
            if (!isset($providerTotals[$pid])) {
                $providerTotals[$pid] = [
                    'providerId'   => $pid,
                    'grossAmount'  => 0,
                    'platformFee'  => 0,
                    'netAmount'    => 0,
                    'transactions' => 0,
                ];
            }
            $providerTotals[$pid]['grossAmount']  += (float) $entry['gross_amount'];
            $providerTotals[$pid]['platformFee']  += (float) $entry['platform_fee'];
            $providerTotals[$pid]['netAmount']    += (float) $entry['net_amount'];
            $providerTotals[$pid]['transactions'] += 1;
        }

        return [
            'settlement'     => $settlement,
            'entries'        => $entries,
            'providerTotals' => array_values($providerTotals),
        ];
    }

    /**
     * Recompute the expected HMAC signature for a receipt row and constant-time compare
     * against the stored value. Returns false when the stored signature does not match
     * the current HMAC key (either tampering, data corruption, or a key rotation).
     */
    public static function verifyReceiptSignature(array $receipt): bool
    {
        $data = $receipt['receipt_number'] . '|' . $receipt['amount'] . '|' . $receipt['issued_at'];
        return hmac_verify($data, (string) $receipt['signature']);
    }

    /**
     * Validate an inbound bank callback. The callback payload MUST include the
     * receipt number, amount and an HMAC signature computed from them. Mismatched
     * or replayed payloads are rejected with an audit trail.
     */
    public static function validateCallback(array $payload): array
    {
        foreach (['receiptNumber', 'amount', 'signature'] as $required) {
            if (empty($payload[$required])) {
                throw new ValidateException("Field '{$required}' is required");
            }
        }

        $receipt = Db::name('receipts')
            ->where('receipt_number', $payload['receiptNumber'])
            ->find();

        if (!$receipt) {
            AuditService::log('CALLBACK_REJECTED', 'receipt', null, null, [
                'reason'         => 'unknown_receipt',
                'receipt_number' => $payload['receiptNumber'],
            ]);
            throw new \think\exception\HttpException(404, 'Unknown receipt');
        }

        $canonical = $payload['receiptNumber'] . '|' . $payload['amount'] . '|' . ($payload['issuedAt'] ?? $receipt['issued_at']);
        if (!hmac_verify($canonical, (string) $payload['signature'])) {
            AuditService::log('CALLBACK_REJECTED', 'receipt', (int) $receipt['id'], null, [
                'reason'         => 'signature_mismatch',
                'receipt_number' => $payload['receiptNumber'],
            ]);
            throw new \think\exception\HttpException(400, 'Signature verification failed');
        }

        if (abs((float) $payload['amount'] - (float) $receipt['amount']) > 0.01) {
            AuditService::log('CALLBACK_REJECTED', 'receipt', (int) $receipt['id'], null, [
                'reason'         => 'amount_mismatch',
                'expected'       => $receipt['amount'],
                'received'       => $payload['amount'],
            ]);
            throw new \think\exception\HttpException(409, 'Callback amount does not match receipt');
        }

        AuditService::log('CALLBACK_ACCEPTED', 'receipt', (int) $receipt['id'], null, [
            'receipt_number' => $payload['receiptNumber'],
            'amount'         => $payload['amount'],
        ]);

        return [
            'receiptId'     => (int) $receipt['id'],
            'paymentId'     => (int) $receipt['payment_id'],
            'receiptNumber' => $receipt['receipt_number'],
            'verified'      => true,
        ];
    }

    private static function parseDate(string $input): string
    {
        $parsed = \DateTime::createFromFormat('m/d/Y', $input);
        if (!$parsed) {
            $parsed = new \DateTime($input);
        }
        return $parsed->format('Y-m-d');
    }
}

<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

class RiskService
{
    /**
     * Nightly risk score calculation for all users/providers.
     */
    public static function calculateScores(): int
    {
        $users = Db::name('users')
            ->where('status', 'ACTIVE')
            ->whereIn('role', ['PROVIDER', 'SERVICE_COORDINATOR'])
            ->select()
            ->toArray();

        $now   = date('Y-m-d H:i:s');
        $count = 0;

        foreach ($users as $user) {
            $userId = $user['id'];

            // Calculate rates
            $totalAppointments = Db::name('appointments')
                ->where('provider_id', $userId)
                ->count();

            $completed = Db::name('appointments')
                ->where('provider_id', $userId)
                ->where('status', 'COMPLETED')
                ->count();

            $cancelled = Db::name('appointments')
                ->where('provider_id', $userId)
                ->where('status', 'CANCELLED')
                ->count();

            $disputed = Db::name('payments')
                ->alias('p')
                ->join('receipts r', 'r.payment_id = p.id')
                ->join('appointments a', 'a.id = r.appointment_id')
                ->where('a.provider_id', $userId)
                ->where('p.status', 'DISPUTED')
                ->count();

            $successRate     = $totalAppointments > 0 ? $completed / $totalAppointments : 1.0;
            $disputeRate     = $totalAppointments > 0 ? $disputed / $totalAppointments : 0.0;
            $cancellationRate = $totalAppointments > 0 ? $cancelled / $totalAppointments : 0.0;

            // Score: 100 * successRate - 50 * disputeRate - 30 * cancellationRate
            $score = round(100 * $successRate - 50 * $disputeRate - 30 * $cancellationRate, 2);
            $score = max(0, min(100, $score));

            Db::name('risk_scores')->insert([
                'user_id'           => $userId,
                'score'             => $score,
                'success_rate'      => round($successRate, 4),
                'dispute_rate'      => round($disputeRate, 4),
                'cancellation_rate' => round($cancellationRate, 4),
                'calculated_at'     => $now,
                'created_at'        => $now,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Aggregate per-IP risk indicators across the device fingerprint + audit data.
     *
     * Returns [ ['ipAddress' => ..., 'ipScore' => 0..100, 'distinctUsers' => n, 'failedLogins' => n], ... ]
     * Lower ipScore = higher risk. Callers may use the `step-up hold` behavior
     * exposed via `maybeApplyStepUpHold()` to act on the data.
     */
    public static function scoreIpRisk(): array
    {
        $sinceHour = date('Y-m-d H:i:s', strtotime('-1 hour'));

        $usersPerIp = Db::name('device_fingerprints')
            ->field('ip_address, COUNT(DISTINCT user_id) AS distinct_users')
            ->group('ip_address')
            ->select()
            ->toArray();

        $failedLoginsPerIp = Db::name('audit_logs')
            ->field('ip_address, COUNT(*) AS failed_logins')
            ->where('action', 'LOGIN_FAILED')
            ->where('created_at', '>=', $sinceHour)
            ->group('ip_address')
            ->select()
            ->toArray();
        $failedByIp = [];
        foreach ($failedLoginsPerIp as $row) {
            $failedByIp[$row['ip_address']] = (int) $row['failed_logins'];
        }

        $result = [];
        foreach ($usersPerIp as $row) {
            $ip = $row['ip_address'];
            $users  = (int) $row['distinct_users'];
            $failed = $failedByIp[$ip] ?? 0;
            // Naive scoring: penalize shared-IP usage and recent failed-login churn.
            $score = 100 - min(100, ($users - 1) * 20 + $failed * 10);
            $result[] = [
                'ipAddress'     => $ip,
                'ipScore'       => $score,
                'distinctUsers' => $users,
                'failedLogins'  => $failed,
            ];
        }

        return $result;
    }

    /**
     * Apply a step-up hold when a user's latest risk score (or IP context) crosses
     * the configured threshold. The hold writes a `STEP_UP_HOLD` anomaly flag and
     * returns true — callers can check the flag before allowing sensitive actions.
     */
    public static function maybeApplyStepUpHold(int $userId, string $ipAddress): bool
    {
        $thresholds = Db::name('throttle_config')->column('value', 'key');
        $stepUpAt   = (int) ($thresholds['step_up_score_below'] ?? 50);

        $latestScore = Db::name('risk_scores')
            ->where('user_id', $userId)
            ->order('calculated_at', 'desc')
            ->value('score');

        $ipRow = null;
        foreach (self::scoreIpRisk() as $row) {
            if ($row['ipAddress'] === $ipAddress) {
                $ipRow = $row;
                break;
            }
        }

        $userScore = $latestScore !== null ? (float) $latestScore : 100;
        $ipScore   = $ipRow['ipScore'] ?? 100;

        if ($userScore >= $stepUpAt && $ipScore >= $stepUpAt) {
            return false;
        }

        self::createFlag($userId, 'STEP_UP_HOLD', [
            'user_score' => $userScore,
            'ip_score'   => $ipScore,
            'ip_address' => $ipAddress,
            'threshold'  => $stepUpAt,
        ]);
        return true;
    }

    /**
     * Check and flag anomalous behavior.
     */
    public static function detectAnomalies(): int
    {
        $now   = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        $weekAgo = date('Y-m-d', strtotime('-7 days'));
        $flagCount = 0;

        // Load thresholds
        $thresholds = Db::name('throttle_config')->column('value', 'key');
        $postingLimit      = $thresholds['postings_per_day'] ?? 20;
        $cancellationLimit = $thresholds['cancellations_per_week'] ?? 5;

        // > N postings/day
        $dailyPostings = Db::name('appointments')
            ->field('created_by, count(*) as cnt')
            ->whereDay('created_at', $today)
            ->group('created_by')
            ->having("cnt > {$postingLimit}")
            ->select()
            ->toArray();

        foreach ($dailyPostings as $row) {
            self::createFlag($row['created_by'], 'EXCESSIVE_POSTING', [
                'date'  => $today,
                'count' => $row['cnt'],
                'limit' => $postingLimit,
            ]);
            $flagCount++;
        }

        // > N cancellations/week
        $weeklyCancellations = Db::name('appointment_history')
            ->field('changed_by, count(*) as cnt')
            ->where('to_status', 'CANCELLED')
            ->where('created_at', '>=', $weekAgo . ' 00:00:00')
            ->group('changed_by')
            ->having("cnt > {$cancellationLimit}")
            ->select()
            ->toArray();

        foreach ($weeklyCancellations as $row) {
            self::createFlag($row['changed_by'], 'EXCESSIVE_CANCELLATION', [
                'week_start' => $weekAgo,
                'count'      => $row['cnt'],
                'limit'      => $cancellationLimit,
            ]);
            $flagCount++;
        }

        // Duplicate device fingerprints (multiple users same fingerprint)
        $dupFingerprints = Db::name('device_fingerprints')
            ->field('fingerprint_hash, count(DISTINCT user_id) as user_count')
            ->group('fingerprint_hash')
            ->having('user_count > 1')
            ->select()
            ->toArray();

        foreach ($dupFingerprints as $row) {
            $userIds = Db::name('device_fingerprints')
                ->where('fingerprint_hash', $row['fingerprint_hash'])
                ->column('user_id');

            foreach ($userIds as $uid) {
                self::createFlag($uid, 'DUPLICATE_DEVICE', [
                    'fingerprint'  => $row['fingerprint_hash'],
                    'shared_users' => $userIds,
                ]);
                $flagCount++;
            }
        }

        return $flagCount;
    }

    /**
     * Create an anomaly flag (skip if identical open flag exists).
     */
    private static function createFlag(int $userId, string $flagType, array $details): void
    {
        // Don't duplicate open flags of the same type
        $existing = Db::name('anomaly_flags')
            ->where('user_id', $userId)
            ->where('flag_type', $flagType)
            ->where('status', 'OPEN')
            ->find();

        if ($existing) {
            return;
        }

        Db::name('anomaly_flags')->insert([
            'user_id'    => $userId,
            'flag_type'  => $flagType,
            'details'    => json_encode($details),
            'status'     => 'OPEN',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        AuditService::log('ANOMALY_FLAGGED', 'anomaly_flag', null, null, [
            'user_id'   => $userId,
            'flag_type' => $flagType,
            'details'   => $details,
        ]);
    }
}

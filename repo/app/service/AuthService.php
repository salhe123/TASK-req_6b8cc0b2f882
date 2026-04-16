<?php
declare(strict_types=1);

namespace app\service;

use app\model\User;
use think\exception\ValidateException;

class AuthService
{
    /**
     * Password complexity rules:
     * - Minimum 10 characters
     * - At least one uppercase letter
     * - At least one lowercase letter
     * - At least one digit
     * - At least one special character
     */
    public static function validatePasswordComplexity(string $password): void
    {
        if (strlen($password) < 10) {
            throw new ValidateException('Password must be at least 10 characters');
        }
        if (!preg_match('/[A-Z]/', $password)) {
            throw new ValidateException('Password must contain at least one uppercase letter');
        }
        if (!preg_match('/[a-z]/', $password)) {
            throw new ValidateException('Password must contain at least one lowercase letter');
        }
        if (!preg_match('/[0-9]/', $password)) {
            throw new ValidateException('Password must contain at least one digit');
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new ValidateException('Password must contain at least one special character');
        }
    }

    /**
     * Authenticate user and return session data.
     */
    public static function login(string $username, string $password, ?array $fingerprint = null): array
    {
        $user = User::where('username', $username)->find();

        if (!$user) {
            AuditService::log('LOGIN_FAILED', 'user', null, null, [
                'username' => $username,
                'reason'   => 'user_not_found',
            ]);
            throw new ValidateException('Invalid credentials');
        }

        if ($user->isLocked()) {
            AuditService::log('LOGIN_BLOCKED', 'user', $user->id, null, [
                'reason' => 'account_locked',
            ]);
            throw new \think\exception\HttpException(423, 'Account locked');
        }

        if ($user->status === 'INACTIVE') {
            AuditService::log('LOGIN_BLOCKED', 'user', $user->id, null, [
                'reason' => 'account_inactive',
            ]);
            throw new \think\exception\HttpException(403, 'Account is inactive');
        }

        if (!$user->verifyPassword($password)) {
            $user->incrementFailedAttempts();

            AuditService::log('LOGIN_FAILED', 'user', $user->id, null, [
                'reason'          => 'invalid_password',
                'failed_attempts' => $user->failed_login_attempts,
            ]);

            if ($user->isLocked()) {
                throw new \think\exception\HttpException(423, 'Account locked after 5 failed attempts');
            }

            throw new ValidateException('Invalid credentials');
        }

        // Successful login
        $user->resetFailedAttempts();

        // Rotate the PHP session ID on login so an attacker who pre-seeded a
        // session cookie can't ride the authenticated session (session fixation
        // defense). If the underlying driver doesn't support regenerate we
        // still proceed with a fresh token below.
        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
        } catch (\Throwable $e) {
            // Non-fatal — token below is still unique per login.
        }

        $sessionData = $user->toSessionArray();
        session('user', $sessionData);
        session('last_activity', time());

        if ($fingerprint) {
            self::recordFingerprint($user->id, $fingerprint);
        }

        // Issue an opaque random token distinct from the PHP session ID. The
        // token is persisted in the session so callers can correlate it but
        // no session identifier ever leaves the server in a response body.
        $token = bin2hex(random_bytes(32));
        session('auth_token', $token);

        AuditService::log('LOGIN_SUCCESS', 'user', $user->id, null, [
            'role' => $user->role,
        ], $user->id);

        return [
            'token'     => $token,
            'expiresIn' => 900,
            'role'      => $user->role,
        ];
    }

    /**
     * Logout and destroy session.
     */
    public static function logout(): void
    {
        $user = session('user');
        if ($user) {
            AuditService::log('LOGOUT', 'user', $user['id']);
        }
        session(null);
    }

    /**
     * Change password for the current user. Also regenerates the session ID
     * so an existing session cannot continue with the old credential bound to
     * it (a password change should invalidate pre-change session state).
     */
    public static function changePassword(int $userId, string $oldPassword, string $newPassword): void
    {
        $user = User::findOrFail($userId);

        if (!$user->verifyPassword($oldPassword)) {
            AuditService::log('PASSWORD_CHANGE_FAILED', 'user', $userId, null, [
                'reason' => 'invalid_old_password',
            ]);
            throw new ValidateException('Current password is incorrect');
        }

        self::validatePasswordComplexity($newPassword);

        $user->password = $newPassword;
        $user->updated_at = date('Y-m-d H:i:s');
        $user->save();

        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
        } catch (\Throwable $e) {
            // Non-fatal.
        }

        AuditService::log('PASSWORD_CHANGED', 'user', $userId);
    }

    /**
     * Record device fingerprint on login.
     */
    private static function recordFingerprint(int $userId, array $fingerprint): void
    {
        $hash = hash('sha256', json_encode([
            $fingerprint['userAgent'] ?? '',
            $fingerprint['screenResolution'] ?? '',
            $fingerprint['timezone'] ?? '',
            $fingerprint['platform'] ?? '',
        ]));

        $now = date('Y-m-d H:i:s');
        $ip = \think\facade\Request::ip();

        $existing = \think\facade\Db::name('device_fingerprints')
            ->where('user_id', $userId)
            ->where('fingerprint_hash', $hash)
            ->find();

        if ($existing) {
            \think\facade\Db::name('device_fingerprints')
                ->where('id', $existing['id'])
                ->update([
                    'last_seen_at' => $now,
                    'ip_address'   => $ip,
                    'updated_at'   => $now,
                ]);
        } else {
            \think\facade\Db::name('device_fingerprints')->insert([
                'user_id'           => $userId,
                'fingerprint_hash'  => $hash,
                'user_agent'        => $fingerprint['userAgent'] ?? '',
                'screen_resolution' => $fingerprint['screenResolution'] ?? null,
                'timezone'          => $fingerprint['timezone'] ?? null,
                'fonts_hash'        => null,
                'ip_address'        => $ip,
                'first_seen_at'     => $now,
                'last_seen_at'      => $now,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
        }
    }
}

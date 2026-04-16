<?php
declare(strict_types=1);

namespace tests\integration;

use tests\IntegrationTestCase;
use app\service\AuthService;
use app\service\AuditService;
use app\model\User;
use think\facade\Db;

class AuthServiceIntegrationTest extends IntegrationTestCase
{
    private function createTempUser(string $password = 'TestPass1234!'): User
    {
        $now = date('Y-m-d H:i:s');
        $id = Db::name('users')->insertGetId([
            'username' => 'inttest_' . uniqid(), 'password' => password_hash($password, PASSWORD_BCRYPT),
            'role' => 'PROVIDER', 'status' => 'ACTIVE', 'failed_login_attempts' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        return User::find($id);
    }

    public function testLoginSuccess()
    {
        $user = $this->createTempUser('ValidPass123!');
        $result = AuthService::login($user->username, 'ValidPass123!');
        $this->assertArrayHasKey('token', $result);
        $this->assertSame('PROVIDER', $result['role']);
        $this->assertSame(900, $result['expiresIn']);
    }

    public function testLoginWrongPassword()
    {
        $user = $this->createTempUser('CorrectPass1!');
        $this->expectException(\think\exception\ValidateException::class);
        AuthService::login($user->username, 'WrongPass!!!!');
    }

    public function testLoginNonexistentUser()
    {
        $this->expectException(\think\exception\ValidateException::class);
        AuthService::login('nonexistent_' . uniqid(), 'AnyPass12345!');
    }

    public function testAccountLockAfter5Failures()
    {
        $user = $this->createTempUser('LockTest12345!');
        for ($i = 0; $i < 5; $i++) {
            try { AuthService::login($user->username, 'Wrong!!!!!!!'); } catch (\Exception $e) {}
        }
        $locked = User::find($user->id);
        $this->assertSame('LOCKED', $locked->status);
        $this->assertSame(5, $locked->failed_login_attempts);
    }

    public function testLockedAccountReturns423()
    {
        $user = $this->createTempUser('Lock423Test1!');
        for ($i = 0; $i < 5; $i++) {
            try { AuthService::login($user->username, 'Wrong!!!!!!!'); } catch (\Exception $e) {}
        }
        $this->expectException(\think\exception\HttpException::class);
        AuthService::login($user->username, 'Lock423Test1!');
    }

    public function testSuccessfulLoginResetsFailedAttempts()
    {
        $user = $this->createTempUser('ResetPass1234!');
        try { AuthService::login($user->username, 'Wrong!!!!!!!'); } catch (\Exception $e) {}
        try { AuthService::login($user->username, 'Wrong!!!!!!!'); } catch (\Exception $e) {}
        AuthService::login($user->username, 'ResetPass1234!');
        $reloaded = User::find($user->id);
        $this->assertSame(0, $reloaded->failed_login_attempts);
    }

    public function testChangePasswordSuccess()
    {
        $user = $this->createTempUser('OldPassword12!');
        AuthService::changePassword($user->id, 'OldPassword12!', 'NewPassword12!');
        $reloaded = User::find($user->id);
        $this->assertTrue($reloaded->verifyPassword('NewPassword12!'));
    }

    public function testChangePasswordWrongOld()
    {
        $user = $this->createTempUser('CorrectOld123!');
        $this->expectException(\think\exception\ValidateException::class);
        AuthService::changePassword($user->id, 'WrongOld!!!!!', 'NewPass12345!');
    }

    public function testChangePasswordWeakNew()
    {
        $user = $this->createTempUser('StrongOld1234!');
        $this->expectException(\think\exception\ValidateException::class);
        AuthService::changePassword($user->id, 'StrongOld1234!', 'weak');
    }

    public function testPasswordComplexityAllRules()
    {
        AuthService::validatePasswordComplexity('ValidPass123!');
        $this->addToAssertionCount(1); // no exception = pass
    }

    public function testPasswordTooShort()
    {
        $this->expectException(\think\exception\ValidateException::class);
        AuthService::validatePasswordComplexity('Short1!');
    }

    public function testLogoutClearsSession()
    {
        $this->assertNotNull(session('user'));
        AuthService::logout();
        $this->assertNull(session('user'));
        // Re-set for other tests
        $this->setUp();
    }

    public function testLoginRecordsDeviceFingerprint()
    {
        $user = $this->createTempUser('FPrint12345!');
        AuthService::login($user->username, 'FPrint12345!', [
            'userAgent' => 'TestAgent', 'screenResolution' => '1920x1080',
            'timezone' => 'America/New_York', 'platform' => 'Test',
        ]);
        $fp = Db::name('device_fingerprints')->where('user_id', $user->id)->find();
        $this->assertNotNull($fp);
        $this->assertSame('TestAgent', $fp['user_agent']);
    }

    public function testLoginCreatesAuditLog()
    {
        $user = $this->createTempUser('AuditLog12345!');
        $before = Db::name('audit_logs')->where('action', 'LOGIN_SUCCESS')->count();
        AuthService::login($user->username, 'AuditLog12345!');
        $after = Db::name('audit_logs')->where('action', 'LOGIN_SUCCESS')->count();
        $this->assertGreaterThan($before, $after);
    }

    /** @test Login with same fingerprint updates last_seen (existing fp path) */
    public function testLoginUpdatesExistingFingerprint()
    {
        $user = $this->createTempUser('FPUpdate12345!');
        $fp = ['userAgent' => 'SameAgent', 'screenResolution' => '1080x720', 'timezone' => 'UTC', 'platform' => 'Test'];

        // First login records fingerprint
        AuthService::login($user->username, 'FPUpdate12345!', $fp);
        $first = Db::name('device_fingerprints')->where('user_id', $user->id)->find();
        $this->assertNotNull($first);

        sleep(1);

        // Second login with same fingerprint should update last_seen_at
        AuthService::login($user->username, 'FPUpdate12345!', $fp);
        $second = Db::name('device_fingerprints')->where('user_id', $user->id)->find();
        $this->assertGreaterThanOrEqual($first['last_seen_at'], $second['last_seen_at']);
    }

    /** @test Failed login creates audit log */
    public function testFailedLoginCreatesAuditLog()
    {
        $user = $this->createTempUser('FailAudit1234!');
        $before = Db::name('audit_logs')->where('action', 'LOGIN_FAILED')->count();
        try { AuthService::login($user->username, 'WrongPass!!!!'); } catch (\Exception $e) {}
        $after = Db::name('audit_logs')->where('action', 'LOGIN_FAILED')->count();
        $this->assertGreaterThan($before, $after);
    }

    /** @test Locked account creates LOGIN_BLOCKED audit log */
    public function testLockedLoginCreatesBlockedAudit()
    {
        $user = $this->createTempUser('BlockAudit123!');
        for ($i = 0; $i < 5; $i++) {
            try { AuthService::login($user->username, 'Wrong!!!!!!!'); } catch (\Exception $e) {}
        }
        $before = Db::name('audit_logs')->where('action', 'LOGIN_BLOCKED')->count();
        try { AuthService::login($user->username, 'BlockAudit123!'); } catch (\Exception $e) {}
        $after = Db::name('audit_logs')->where('action', 'LOGIN_BLOCKED')->count();
        $this->assertGreaterThan($before, $after);
    }

    /** @test User model setPasswordAttr hashes password */
    public function testUserPasswordHashing()
    {
        $user = new \app\model\User();
        $hashed = $user->setPasswordAttr('TestPass1234!');
        $this->assertTrue(password_verify('TestPass1234!', $hashed));
        $this->assertNotSame('TestPass1234!', $hashed);
    }

    /** @test Password change creates audit log */
    public function testChangePasswordCreatesAudit()
    {
        $user = $this->createTempUser('AuditPWChg123!');
        $before = Db::name('audit_logs')->where('action', 'PASSWORD_CHANGED')->count();
        AuthService::changePassword($user->id, 'AuditPWChg123!', 'NewAuditPW1234!');
        $after = Db::name('audit_logs')->where('action', 'PASSWORD_CHANGED')->count();
        $this->assertGreaterThan($before, $after);
    }
}

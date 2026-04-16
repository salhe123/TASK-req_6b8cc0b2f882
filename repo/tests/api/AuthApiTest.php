<?php
declare(strict_types=1);

namespace tests\api;

use tests\ApiTestCase;

class AuthApiTest extends ApiTestCase
{
    /** @test POST /api/auth/login with valid credentials returns token and role */
    public function testLoginSuccess()
    {
        $resp = $this->post('/api/auth/login', [
            'username' => 'admin',
            'password' => 'Admin12345!',
        ]);

        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('expiresIn', $data);
        $this->assertArrayHasKey('role', $data);
        $this->assertSame('SYSTEM_ADMIN', $data['role']);
        $this->assertSame(900, $data['expiresIn']);
    }

    /** @test POST /api/auth/login with wrong password returns 401 */
    public function testLoginWrongPassword()
    {
        $resp = $this->post('/api/auth/login', [
            'username' => 'admin',
            'password' => 'WrongPassword1!',
        ]);

        $this->assertResponseCode($resp, 401);
        $this->assertArrayHasKey('message', $resp['body']);
    }

    /** @test POST /api/auth/login with empty fields returns 400 */
    public function testLoginEmptyFields()
    {
        $resp = $this->post('/api/auth/login', []);
        $this->assertResponseCode($resp, 400);
    }

    /** @test POST /api/auth/login with nonexistent user returns 401 */
    public function testLoginNonexistentUser()
    {
        $resp = $this->post('/api/auth/login', [
            'username' => 'ghost_user_' . time(),
            'password' => 'DoesntMatter1!',
        ]);
        $this->assertResponseCode($resp, 401);
    }

    /** @test POST /api/auth/logout clears session */
    public function testLogout()
    {
        $this->loginAsAdmin();
        $resp = $this->post('/api/auth/logout');
        $this->assertResponseCode($resp, 200);

        // Subsequent authenticated request should fail
        $resp2 = $this->get('/api/admin/users');
        $this->assertResponseCode($resp2, 401);
    }

    /** @test Each role can login and gets correct role back */
    public function testAllRolesLogin()
    {
        $roles = [
            ['admin', 'Admin12345!', 'SYSTEM_ADMIN'],
            ['planner1', 'Planner12345!', 'PRODUCTION_PLANNER'],
            ['coordinator1', 'Coordinator1!', 'SERVICE_COORDINATOR'],
            ['provider1', 'Provider1234!', 'PROVIDER'],
            ['reviewer1', 'Reviewer1234!', 'REVIEWER'],
            ['moderator1', 'Moderator123!', 'CONTENT_MODERATOR'],
            ['finance1', 'Finance12345!', 'FINANCE_CLERK'],
        ];

        foreach ($roles as [$user, $pass, $expectedRole]) {
            $resp = $this->loginAs($user, $pass);
            $this->assertResponseCode($resp, 200);
            $data = $this->getData($resp);
            $this->assertSame($expectedRole, $data['role'], "Role mismatch for {$user}");
        }
    }

    /** @test Unauthenticated request to protected route returns 401 */
    public function testUnauthenticatedBlocked()
    {
        self::$sessionCookie = null;
        $resp = $this->get('/api/admin/dashboard');
        $this->assertResponseCode($resp, 401);
    }

    /** @test Health endpoint works without auth */
    public function testHealthEndpoint()
    {
        self::$sessionCookie = null;
        $resp = $this->get('/api/health');
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertSame('ok', $data['status']);
        $this->assertArrayHasKey('version', $data);
    }

    /** @test POST /api/auth/change-password with valid old password */
    public function testChangePasswordSuccess()
    {
        // Create a temp user, login, change password, verify new password works
        $this->loginAsAdmin();
        $username = 'pwchange_' . time();
        $resp = $this->post('/api/admin/users', [
            'username' => $username,
            'password' => 'OldPass12345!',
            'role'     => 'PROVIDER',
        ]);
        $this->assertResponseCode($resp, 201);

        // Login as the new user
        $this->loginAs($username, 'OldPass12345!');

        // Change password
        $resp = $this->post('/api/auth/change-password', [
            'oldPassword' => 'OldPass12345!',
            'newPassword' => 'NewPass12345!',
        ]);
        $this->assertResponseCode($resp, 200);

        // Verify new password works
        $resp = $this->loginAs($username, 'NewPass12345!');
        $this->assertResponseCode($resp, 200);
    }

    /** @test POST /api/auth/change-password with wrong old password fails */
    public function testChangePasswordWrongOld()
    {
        $this->loginAsAdmin();
        $resp = $this->post('/api/auth/change-password', [
            'oldPassword' => 'WrongOldPass1!',
            'newPassword' => 'NewPass12345!',
        ]);
        $this->assertResponseCode($resp, 400);
    }

    /** @test POST /api/auth/change-password with weak new password fails */
    public function testChangePasswordWeakNew()
    {
        $this->loginAsAdmin();
        $resp = $this->post('/api/auth/change-password', [
            'oldPassword' => 'Admin12345!',
            'newPassword' => 'short',
        ]);
        $this->assertResponseCode($resp, 400);
    }

    /** @test Account lock after repeated failures then login blocked */
    public function testAccountLockAfterFailures()
    {
        $this->loginAsAdmin();
        $username = 'locktest_' . time();
        $this->post('/api/admin/users', [
            'username' => $username,
            'password' => 'LockTestPass1!',
            'role'     => 'PROVIDER',
        ]);

        // Fail 5 times
        for ($i = 0; $i < 5; $i++) {
            $this->loginAs($username, 'WrongPassword!');
        }

        // Now even correct password should be blocked (423)
        $resp = $this->loginAs($username, 'LockTestPass1!');
        $this->assertResponseCode($resp, 423);
    }
}

<?php
declare(strict_types=1);

namespace tests\api;

use tests\ApiTestCase;

class UserApiTest extends ApiTestCase
{
    /** @test GET /api/admin/users returns paginated list */
    public function testListUsers()
    {
        $this->loginAsAdmin();
        $resp = $this->get('/api/admin/users');

        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertArrayHasKey('list', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertGreaterThanOrEqual(7, $data['total']);

        // Validate user structure
        $user = $data['list'][0];
        $this->assertArrayHasKey('id', $user);
        $this->assertArrayHasKey('username', $user);
        $this->assertArrayHasKey('role', $user);
        $this->assertArrayHasKey('status', $user);
        // Password should be hidden
        $this->assertArrayNotHasKey('password', $user);
    }

    /** @test GET /api/admin/users?role=PROVIDER filters by role */
    public function testListUsersByRole()
    {
        $this->loginAsAdmin();
        $resp = $this->get('/api/admin/users', ['role' => 'PROVIDER']);

        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        foreach ($data['list'] as $user) {
            $this->assertSame('PROVIDER', $user['role']);
        }
    }

    /** @test POST /api/admin/users creates a new user */
    public function testCreateUser()
    {
        $this->loginAsAdmin();
        $username = 'testuser_' . time();
        $resp = $this->post('/api/admin/users', [
            'username' => $username,
            'password' => 'TestPass12345!',
            'role'     => 'PROVIDER',
        ]);

        $this->assertResponseCode($resp, 201);
        $data = $this->getData($resp);
        $this->assertSame($username, $data['username']);
        $this->assertSame('PROVIDER', $data['role']);
        $this->assertSame('ACTIVE', $data['status']);
    }

    /** @test POST /api/admin/users rejects duplicate username */
    public function testCreateDuplicateUser()
    {
        $this->loginAsAdmin();
        $resp = $this->post('/api/admin/users', [
            'username' => 'admin',
            'password' => 'TestPass12345!',
            'role'     => 'PROVIDER',
        ]);
        $this->assertResponseCode($resp, 409);
    }

    /** @test PUT /api/admin/users/{id}/lock locks an account */
    public function testLockUser()
    {
        $this->loginAsAdmin();

        // Create a user to lock
        $username = 'lockme_' . time();
        $createResp = $this->post('/api/admin/users', [
            'username' => $username,
            'password' => 'LockMePass123!',
            'role'     => 'PROVIDER',
        ]);
        $userId = $this->getData($createResp)['id'];

        $resp = $this->put("/api/admin/users/{$userId}/lock");
        $this->assertResponseCode($resp, 200);
        $this->assertSame('LOCKED', $this->getData($resp)['status']);
    }

    /** @test PUT /api/admin/users/{id}/unlock unlocks an account */
    public function testUnlockUser()
    {
        $this->loginAsAdmin();

        $username = 'unlockme_' . time();
        $createResp = $this->post('/api/admin/users', [
            'username' => $username,
            'password' => 'UnlockPass123!',
            'role'     => 'PROVIDER',
        ]);
        $userId = $this->getData($createResp)['id'];

        $this->put("/api/admin/users/{$userId}/lock");
        $resp = $this->put("/api/admin/users/{$userId}/unlock");
        $this->assertResponseCode($resp, 200);
        $this->assertSame('ACTIVE', $this->getData($resp)['status']);
    }

    /** @test Non-admin cannot access user management */
    public function testNonAdminBlocked()
    {
        $this->loginAsProvider();
        $resp = $this->get('/api/admin/users');
        $this->assertResponseCode($resp, 403);
    }

    /** @test PUT /api/admin/users/{id} updates role */
    public function testUpdateUserRole()
    {
        $this->loginAsAdmin();
        $username = 'updateme_' . time();
        $resp = $this->post('/api/admin/users', [
            'username' => $username, 'password' => 'UpdatePass123!', 'role' => 'PROVIDER',
        ]);
        $userId = $this->getData($resp)['id'];

        $resp = $this->put("/api/admin/users/{$userId}", ['role' => 'REVIEWER']);
        $this->assertResponseCode($resp, 200);
        $this->assertSame('REVIEWER', $this->getData($resp)['role']);
    }

    /** @test PUT /api/admin/users/{id} with invalid role returns 400 */
    public function testUpdateUserInvalidRole()
    {
        $this->loginAsAdmin();
        $username = 'badrole_' . time();
        $resp = $this->post('/api/admin/users', [
            'username' => $username, 'password' => 'BadRolePass1!', 'role' => 'PROVIDER',
        ]);
        $userId = $this->getData($resp)['id'];

        $resp = $this->put("/api/admin/users/{$userId}", ['role' => 'SUPER_ADMIN']);
        $this->assertResponseCode($resp, 400);
    }

    /** @test POST /api/admin/users with weak password returns 400 */
    public function testCreateUserWeakPassword()
    {
        $this->loginAsAdmin();
        $resp = $this->post('/api/admin/users', [
            'username' => 'weakpw_' . time(), 'password' => 'short', 'role' => 'PROVIDER',
        ]);
        $this->assertResponseCode($resp, 400);
    }

    /** @test GET /api/admin/users pagination works */
    public function testUserPagination()
    {
        $this->loginAsAdmin();
        $resp = $this->get('/api/admin/users', ['page' => 1, 'size' => 2]);
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertLessThanOrEqual(2, count($data['list']));
        $this->assertSame(1, $data['page']);
    }
}

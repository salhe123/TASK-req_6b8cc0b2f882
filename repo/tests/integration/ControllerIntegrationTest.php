<?php
declare(strict_types=1);

namespace tests\integration;

use tests\IntegrationTestCase;
use think\App;

/**
 * Tests controller methods directly in-process for coverage.
 * API tests validate HTTP routing; these validate controller logic generates coverage.
 */
class ControllerIntegrationTest extends IntegrationTestCase
{
    private function callController(string $class, string $method, array $params = [])
    {
        $app = self::$app;
        $controller = $app->make($class);
        return call_user_func_array([$controller, $method], $params);
    }

    // ─── Auth Controller ───

    public function testAuthLoginMissingFields()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = new \think\Request();
        $request->withInput(json_encode([]));
        self::$app->instance('think\Request', $request);

        $ctrl = self::$app->make(\app\controller\auth\Auth::class);
        $resp = $ctrl->login();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(400, $data['code']);
    }

    public function testAuthLoginValid()
    {
        $request = new \think\Request();
        $request->withInput(json_encode(['username' => 'admin', 'password' => 'Admin12345!']));
        self::$app->instance('think\Request', $request);

        $ctrl = self::$app->make(\app\controller\auth\Auth::class);
        $resp = $ctrl->login();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
        $this->assertSame('SYSTEM_ADMIN', $data['data']['role']);
    }

    public function testAuthLogout()
    {
        $ctrl = self::$app->make(\app\controller\auth\Auth::class);
        $resp = $ctrl->logout();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
        $this->setUp(); // restore session
    }

    public function testAuthChangePasswordMissing()
    {
        $request = new \think\Request();
        $request->withInput(json_encode([]));
        self::$app->instance('think\Request', $request);
        $ctrl = self::$app->make(\app\controller\auth\Auth::class);
        $resp = $ctrl->changePassword();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(400, $data['code']);
    }

    // ─── User Controller ───

    public function testUserIndex()
    {
        $request = new \think\Request();
        $request->withGet(['page' => 1, 'size' => 5]);
        self::$app->instance('think\Request', $request);
        $ctrl = self::$app->make(\app\controller\admin\User::class);
        $resp = $ctrl->index();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
        $this->assertArrayHasKey('list', $data['data']);
    }

    public function testUserCreateValid()
    {
        $request = new \think\Request();
        $request->withInput(json_encode([
            'username' => 'ctrltest_' . uniqid(), 'password' => 'CtrlTest1234!', 'role' => 'PROVIDER',
        ]));
        self::$app->instance('think\Request', $request);
        $ctrl = self::$app->make(\app\controller\admin\User::class);
        $resp = $ctrl->create();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(201, $data['code']);
    }

    public function testUserCreateMissingFields()
    {
        $request = new \think\Request();
        $request->withInput(json_encode(['username' => 'x']));
        self::$app->instance('think\Request', $request);
        $ctrl = self::$app->make(\app\controller\admin\User::class);
        $resp = $ctrl->create();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(400, $data['code']);
    }

    public function testUserLockUnlock()
    {
        // Create user first
        $now = date('Y-m-d H:i:s');
        $id = \think\facade\Db::name('users')->insertGetId([
            'username' => 'lockctrl_' . uniqid(), 'password' => password_hash('Test12345!', PASSWORD_BCRYPT),
            'role' => 'PROVIDER', 'status' => 'ACTIVE', 'failed_login_attempts' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $ctrl = self::$app->make(\app\controller\admin\User::class);
        $resp = $ctrl->lock($id);
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
        $this->assertSame('LOCKED', $data['data']['status']);

        $resp = $ctrl->unlock($id);
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
        $this->assertSame('ACTIVE', $data['data']['status']);
    }

    public function testUserNotFound()
    {
        $ctrl = self::$app->make(\app\controller\admin\User::class);
        $resp = $ctrl->lock(999999);
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(404, $data['code']);
    }

    // ─── Dashboard Controller ───

    public function testDashboardIndex()
    {
        $ctrl = self::$app->make(\app\controller\admin\Dashboard::class);
        $resp = $ctrl->index();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
        $this->assertArrayHasKey('totalUsers', $data['data']);
        $this->assertArrayHasKey('totalAppointments', $data['data']);
        $this->assertArrayHasKey('pendingModeration', $data['data']);
    }

    // ─── Audit Controller ───

    public function testAuditLogs()
    {
        $request = new \think\Request();
        $request->withGet(['page' => 1, 'size' => 10]);
        self::$app->instance('think\Request', $request);
        $ctrl = self::$app->make(\app\controller\admin\Audit::class);
        $resp = $ctrl->logs();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
        $this->assertArrayHasKey('list', $data['data']);
    }

    // ─── Risk Controller ───

    public function testRiskScores()
    {
        $request = new \think\Request();
        $request->withGet([]);
        self::$app->instance('think\Request', $request);
        $ctrl = self::$app->make(\app\controller\admin\Risk::class);
        $resp = $ctrl->scores();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
    }

    public function testRiskFlags()
    {
        $ctrl = self::$app->make(\app\controller\admin\Risk::class);
        $resp = $ctrl->flags();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
    }

    public function testRiskThrottles()
    {
        $ctrl = self::$app->make(\app\controller\admin\Risk::class);
        $resp = $ctrl->throttles();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
    }

    // ─── Appointment Controller ───

    public function testAppointmentIndex()
    {
        $request = new \think\Request();
        $request->withGet(['page' => 1, 'size' => 5]);
        self::$app->instance('think\Request', $request);
        $ctrl = self::$app->make(\app\controller\appointment\Appointment::class);
        $resp = $ctrl->index();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
    }

    public function testAppointmentCreateAndConfirm()
    {
        $request = new \think\Request();
        $request->withInput(json_encode([
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '04/30/2026 10:00 AM', 'location' => 'Ctrl Test',
        ]));
        self::$app->instance('think\Request', $request);
        $ctrl = self::$app->make(\app\controller\appointment\Appointment::class);
        $resp = $ctrl->create();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(201, $data['code']);
        $id = $data['data']['id'];

        $resp = $ctrl->confirm($id);
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
        $this->assertSame('CONFIRMED', $data['data']['status']);

        $resp = $ctrl->read($id);
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);

        $resp = $ctrl->history($id);
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
    }

    public function testAppointmentNotFound()
    {
        $ctrl = self::$app->make(\app\controller\appointment\Appointment::class);
        $resp = $ctrl->read(999999);
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(404, $data['code']);
    }

    // ─── Catalog Controller ───

    public function testCatalogCreateAndSubmit()
    {
        $request = new \think\Request();
        $request->withInput(json_encode([
            'name' => 'Ctrl CPU ' . uniqid(), 'category' => 'CPU',
            'specs' => ['clockSpeed' => '3.0 GHz', 'cores' => 8],
        ]));
        self::$app->instance('think\Request', $request);
        $ctrl = self::$app->make(\app\controller\catalog\Product::class);
        $resp = $ctrl->create();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(201, $data['code']);
        $id = $data['data']['id'];

        $resp = $ctrl->submit($id);
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
    }

    public function testCatalogIndex()
    {
        $request = new \think\Request();
        $request->withGet(['page' => 1, 'size' => 5]);
        self::$app->instance('think\Request', $request);
        $ctrl = self::$app->make(\app\controller\catalog\Product::class);
        $resp = $ctrl->index();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
    }

    // ─── Finance Controllers ───

    public function testPaymentIndex()
    {
        $request = new \think\Request();
        $request->withGet(['page' => 1, 'size' => 5]);
        self::$app->instance('think\Request', $request);
        $ctrl = self::$app->make(\app\controller\finance\Payment::class);
        $resp = $ctrl->index();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
    }

    public function testReceiptIndex()
    {
        $request = new \think\Request();
        $request->withGet([]);
        self::$app->instance('think\Request', $request);
        $ctrl = self::$app->make(\app\controller\finance\Receipt::class);
        $resp = $ctrl->index();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
    }

    public function testSettlementIndex()
    {
        $request = new \think\Request();
        $request->withGet([]);
        self::$app->instance('think\Request', $request);
        $ctrl = self::$app->make(\app\controller\finance\Settlement::class);
        $resp = $ctrl->index();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
    }

    // ─── Moderation Controller ───

    public function testModerationPending()
    {
        $request = new \think\Request();
        $request->withGet([]);
        self::$app->instance('think\Request', $request);
        $ctrl = self::$app->make(\app\controller\moderation\Moderation::class);
        $resp = $ctrl->pending();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
    }

    // ─── Review Controller ───

    public function testReviewListReviewers()
    {
        $ctrl = self::$app->make(\app\controller\review\Review::class);
        $resp = $ctrl->listReviewers();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
    }

    public function testReviewListScorecards()
    {
        $ctrl = self::$app->make(\app\controller\review\Review::class);
        $resp = $ctrl->listScorecards();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
    }

    // ─── Production Controllers ───

    public function testWorkCenterIndex()
    {
        $request = new \think\Request();
        $request->withGet([]);
        self::$app->instance('think\Request', $request);
        $ctrl = self::$app->make(\app\controller\production\WorkCenter::class);
        $resp = $ctrl->index();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
    }

    public function testMpsIndex()
    {
        $request = new \think\Request();
        $request->withGet([]);
        self::$app->instance('think\Request', $request);
        $ctrl = self::$app->make(\app\controller\production\Mps::class);
        $resp = $ctrl->index();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
    }

    public function testWorkOrderIndex()
    {
        $request = new \think\Request();
        $request->withGet(['page' => 1, 'size' => 5]);
        self::$app->instance('think\Request', $request);
        $ctrl = self::$app->make(\app\controller\production\WorkOrder::class);
        $resp = $ctrl->index();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
    }

    public function testCapacityIndex()
    {
        $request = new \think\Request();
        $request->withGet([]);
        self::$app->instance('think\Request', $request);
        $ctrl = self::$app->make(\app\controller\production\Capacity::class);
        $resp = $ctrl->index();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
    }

    // ─── Provider Queue Controller ───

    public function testProviderQueue()
    {
        $this->setUser(4, 'provider1', 'PROVIDER');
        $request = new \think\Request();
        $request->withGet(['date' => date('m/d/Y')]);
        self::$app->instance('think\Request', $request);
        $ctrl = self::$app->make(\app\controller\provider\Queue::class);
        $resp = $ctrl->index();
        $data = json_decode($resp->getContent(), true);
        $this->assertSame(200, $data['code']);
        $this->assertArrayHasKey('appointments', $data['data']);
    }
}

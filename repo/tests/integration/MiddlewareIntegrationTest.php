<?php
declare(strict_types=1);

namespace tests\integration;

use tests\IntegrationTestCase;
use app\middleware\AuthMiddleware;
use app\middleware\ThrottleMiddleware;
use think\Request;
use think\facade\Cache;

class MiddlewareIntegrationTest extends IntegrationTestCase
{
    private function makeRequest(string $method = 'GET', string $uri = '/test'): Request
    {
        $request = new Request();
        $request->setMethod($method);
        $request->setPathinfo($uri);
        return $request;
    }

    private function passThrough(): \Closure
    {
        return function ($request) {
            return json_success(['passed' => true]);
        };
    }

    // ─── AuthMiddleware ───

    public function testAuthMiddlewareBlocksNoSession()
    {
        session('user', null);
        $mw = new AuthMiddleware();
        $resp = $mw->handle($this->makeRequest(), $this->passThrough());
        $content = json_decode($resp->getContent(), true);
        $this->assertSame(401, $content['code']);
        $this->setUp(); // restore session
    }

    public function testAuthMiddlewareAllowsValidSession()
    {
        $mw = new AuthMiddleware();
        $resp = $mw->handle($this->makeRequest(), $this->passThrough());
        $content = json_decode($resp->getContent(), true);
        $this->assertSame(200, $content['code']);
    }

    public function testAuthMiddlewareExpiredSession()
    {
        session('last_activity', time() - 901);
        $mw = new AuthMiddleware();
        $resp = $mw->handle($this->makeRequest(), $this->passThrough());
        $content = json_decode($resp->getContent(), true);
        $this->assertSame(401, $content['code']);
        $this->setUp();
    }

    public function testAuthMiddlewareUpdatesLastActivity()
    {
        session('last_activity', time() - 100);
        $mw = new AuthMiddleware();
        $mw->handle($this->makeRequest(), $this->passThrough());
        $this->assertGreaterThan(time() - 5, session('last_activity'));
    }

    // ─── RBAC via AuthMiddleware parameter ───

    public function testRbacAdminAlwaysPasses()
    {
        $this->setUser(1, 'admin', 'SYSTEM_ADMIN');
        $mw = new AuthMiddleware();
        $resp = $mw->handle($this->makeRequest(), $this->passThrough(), 'FINANCE_CLERK');
        $content = json_decode($resp->getContent(), true);
        $this->assertSame(200, $content['code']);
    }

    public function testRbacMatchingRolePasses()
    {
        $this->setUser(7, 'finance1', 'FINANCE_CLERK');
        $mw = new AuthMiddleware();
        $resp = $mw->handle($this->makeRequest(), $this->passThrough(), 'FINANCE_CLERK');
        $content = json_decode($resp->getContent(), true);
        $this->assertSame(200, $content['code']);
    }

    public function testRbacWrongRoleBlocked()
    {
        $this->setUser(4, 'provider1', 'PROVIDER');
        $mw = new AuthMiddleware();
        $resp = $mw->handle($this->makeRequest(), $this->passThrough(), 'FINANCE_CLERK');
        $content = json_decode($resp->getContent(), true);
        $this->assertSame(403, $content['code']);
    }

    public function testRbacNoRoleRequiredPasses()
    {
        $this->setUser(4, 'provider1', 'PROVIDER');
        $mw = new AuthMiddleware();
        $resp = $mw->handle($this->makeRequest(), $this->passThrough(), '');
        $content = json_decode($resp->getContent(), true);
        $this->assertSame(200, $content['code']);
    }

    // ─── ThrottleMiddleware ───

    public function testThrottleAllowsUnderLimit()
    {
        $mw = new ThrottleMiddleware();
        // Force a known limit so the test is independent of DB tuning.
        Cache::set('throttle:cfg:requests_per_minute', 60, 60);
        Cache::delete('throttle:rpm:1');
        $resp = $mw->handle($this->makeRequest(), $this->passThrough());
        $content = json_decode($resp->getContent(), true);
        $this->assertSame(200, $content['code']);
    }

    public function testThrottleBlocksOverLimit()
    {
        $mw = new ThrottleMiddleware();
        Cache::set('throttle:cfg:requests_per_minute', 60, 60);
        Cache::set('throttle:rpm:1', 60, 60);
        $resp = $mw->handle($this->makeRequest(), $this->passThrough());
        $content = json_decode($resp->getContent(), true);
        $this->assertSame(429, $content['code']);
        Cache::delete('throttle:rpm:1');
    }

    public function testThrottleSkipsNoSession()
    {
        session('user', null);
        $mw = new ThrottleMiddleware();
        $resp = $mw->handle($this->makeRequest(), $this->passThrough());
        $content = json_decode($resp->getContent(), true);
        $this->assertSame(200, $content['code']);
        $this->setUp();
    }

    public function testAppointmentHourScopeBlocksOverCap()
    {
        $mw = new ThrottleMiddleware();
        Cache::set('throttle:cfg:appointments_per_hour', 10, 60);
        Cache::set('throttle:appt_hour:1', 10, 3600);
        $resp = $mw->handle($this->makeRequest('POST', '/api/appointments'), $this->passThrough(), 'appointments_per_hour');
        $content = json_decode($resp->getContent(), true);
        $this->assertSame(429, $content['code']);
        Cache::delete('throttle:appt_hour:1');
    }
}

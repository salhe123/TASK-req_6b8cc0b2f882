<?php
declare(strict_types=1);

namespace tests\unit;

use tests\TestCase;

/**
 * Pure-unit tests for middleware-related logic that does not require a running
 * app container. Behavioral tests that exercise the actual AuthMiddleware /
 * ThrottleMiddleware classes live in tests/integration/MiddlewareIntegrationTest.php.
 */
class MiddlewareTest extends TestCase
{
    public function testRoleCommaListParsing()
    {
        // The parser AuthMiddleware uses for required roles
        $parse = function (string $roles): array {
            return array_map('trim', explode(',', $roles));
        };
        $this->assertSame(['SYSTEM_ADMIN', 'FINANCE_CLERK'], $parse('SYSTEM_ADMIN,FINANCE_CLERK'));
        $this->assertSame(['SYSTEM_ADMIN', 'FINANCE_CLERK'], $parse('SYSTEM_ADMIN, FINANCE_CLERK'));
        $this->assertSame([''], $parse(''));
    }

    public function testRoleAuthorizationDecision()
    {
        $decide = function (string $userRole, string $required): bool {
            if ($userRole === 'SYSTEM_ADMIN') {
                return true;
            }
            $allowed = array_map('trim', explode(',', $required));
            return in_array($userRole, $allowed, true);
        };
        $this->assertTrue($decide('SYSTEM_ADMIN', 'FINANCE_CLERK'), 'admin bypass');
        $this->assertTrue($decide('FINANCE_CLERK', 'FINANCE_CLERK'), 'exact match');
        $this->assertTrue($decide('PROVIDER', 'SERVICE_COORDINATOR,PROVIDER'), 'member of list');
        $this->assertFalse($decide('PROVIDER', 'FINANCE_CLERK'), 'disallowed role');
        $this->assertFalse($decide('REVIEWER', 'SYSTEM_ADMIN'), 'non-admin non-match');
    }

    public function testAllDefinedRolesMatchSchemaEnum()
    {
        $roles = [
            'PRODUCTION_PLANNER', 'SERVICE_COORDINATOR', 'PROVIDER', 'REVIEWER',
            'CONTENT_MODERATOR', 'FINANCE_CLERK', 'SYSTEM_ADMIN',
        ];
        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql');
        $this->assertNotFalse($schema, 'schema.sql must be readable');
        foreach ($roles as $r) {
            $this->assertStringContainsString(
                "'{$r}'",
                $schema,
                "role {$r} must appear in schema.sql enum"
            );
        }
    }

    public function testThrottleCounterBoundary()
    {
        // Reproduces the "$count >= $limit" check in ThrottleMiddleware.
        $overLimit = function (int $count, int $limit): bool {
            return $count >= $limit;
        };
        $this->assertFalse($overLimit(59, 60), 'below limit passes');
        $this->assertTrue($overLimit(60, 60), 'at limit blocks');
        $this->assertTrue($overLimit(61, 60), 'over limit blocks');
    }

    public function testSessionIdleBoundary()
    {
        // Reproduces the "time() - last_activity > 900" check in AuthMiddleware.
        $expired = function (int $last): bool {
            return (time() - $last) > 900;
        };
        $this->assertFalse($expired(time() - 899));
        $this->assertFalse($expired(time() - 900));
        $this->assertTrue($expired(time() - 901));
    }

    public function testAccountLockThresholdBoundary()
    {
        // The User model locks at >= 5 failed attempts.
        $shouldLock = function (int $attempts): bool {
            return $attempts >= 5;
        };
        $this->assertFalse($shouldLock(4));
        $this->assertTrue($shouldLock(5));
        $this->assertTrue($shouldLock(6));
    }
}

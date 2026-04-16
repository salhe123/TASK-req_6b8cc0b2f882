<?php
declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use think\App;
use think\facade\Db;

/**
 * Base class for integration tests that run IN-PROCESS with a real DB.
 * These tests generate pcov coverage because the production code runs
 * in the same PHP process as PHPUnit.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected static ?App $app = null;
    protected static bool $booted = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$booted) {
            self::$app = new App(dirname(__DIR__));
            self::$app->initialize();
            self::$booted = true;
        }

        // Set a default session for authenticated tests
        session('user', [
            'id'       => 1,
            'username' => 'admin',
            'role'     => 'SYSTEM_ADMIN',
            'status'   => 'ACTIVE',
        ]);
        session('last_activity', time());
    }

    protected function setUser(int $id, string $username, string $role): void
    {
        session('user', [
            'id'       => $id,
            'username' => $username,
            'role'     => $role,
            'status'   => 'ACTIVE',
        ]);
    }
}

<?php
declare(strict_types=1);

namespace tests\integration;

use tests\IntegrationTestCase;
use app\service\AuditService;
use think\facade\Db;

class AuditServiceIntegrationTest extends IntegrationTestCase
{
    public function testLogCreatesEntry()
    {
        $before = Db::name('audit_logs')->count();
        AuditService::log('TEST_ACTION', 'test_entity', 1, null, ['key' => 'value']);
        $after = Db::name('audit_logs')->count();
        $this->assertSame($before + 1, $after);

        $entry = Db::name('audit_logs')->order('id desc')->find();
        $this->assertSame('TEST_ACTION', $entry['action']);
        $this->assertSame('test_entity', $entry['entity_type']);
    }

    public function testLogWithBeforeAfter()
    {
        AuditService::log('UPDATE_ACTION', 'entity', 5, ['status' => 'old'], ['status' => 'new']);
        $entry = Db::name('audit_logs')->order('id desc')->find();
        $before = json_decode($entry['before_data'], true);
        $after = json_decode($entry['after_data'], true);
        $this->assertSame('old', $before['status']);
        $this->assertSame('new', $after['status']);
    }

    public function testLogWithNullUser()
    {
        session('user', null);
        AuditService::log('SYSTEM_ACTION', 'system', null, null, null, null);
        $entry = Db::name('audit_logs')->order('id desc')->find();
        $this->assertNull($entry['user_id']);
        $this->setUp(); // restore session
    }

    public function testLogRecordsIpAddress()
    {
        AuditService::log('IP_TEST', 'test', 1);
        $entry = Db::name('audit_logs')->order('id desc')->find();
        $this->assertNotEmpty($entry['ip_address']);
    }
}

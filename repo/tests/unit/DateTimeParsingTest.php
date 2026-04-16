<?php
declare(strict_types=1);

namespace tests\unit;

use tests\TestCase;
use app\service\AppointmentService;

class DateTimeParsingTest extends TestCase
{
    /** @test Parse MM/DD/YYYY hh:mm AM format */
    public function testParseAmFormat()
    {
        $result = AppointmentService::parseDateTime('04/17/2026 10:30 AM');
        $this->assertSame('2026-04-17 10:30:00', $result);
    }

    /** @test Parse MM/DD/YYYY hh:mm PM format */
    public function testParsePmFormat()
    {
        $result = AppointmentService::parseDateTime('04/17/2026 02:30 PM');
        $this->assertSame('2026-04-17 14:30:00', $result);
    }

    /** @test Parse 12:00 PM is noon */
    public function testParse12Pm()
    {
        $result = AppointmentService::parseDateTime('04/17/2026 12:00 PM');
        $this->assertSame('2026-04-17 12:00:00', $result);
    }

    /** @test Parse 12:00 AM is midnight */
    public function testParse12Am()
    {
        $result = AppointmentService::parseDateTime('04/17/2026 12:00 AM');
        $this->assertSame('2026-04-17 00:00:00', $result);
    }

    /** @test Public parser rejects Y-m-d H:i:s (external API must use MM/DD/YYYY) */
    public function testParseStandardFormatRejectedFromPublicParser()
    {
        $this->expectException(\think\exception\ValidateException::class);
        AppointmentService::parseDateTime('2026-04-17 14:30:00');
    }

    /** @test Internal parser still accepts Y-m-d H:i:s (seeders / cron) */
    public function testInternalParserAcceptsStandardFormat()
    {
        $result = AppointmentService::parseDateTimeInternal('2026-04-17 14:30:00');
        $this->assertSame('2026-04-17 14:30:00', $result);
    }

    /** @test Invalid format throws exception */
    public function testInvalidFormatThrows()
    {
        $this->expectException(\think\exception\ValidateException::class);
        AppointmentService::parseDateTime('not-a-date');
    }
}

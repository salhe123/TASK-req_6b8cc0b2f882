<?php
declare(strict_types=1);

namespace tests\unit;

use tests\TestCase;
use app\service\AuthService;
use think\exception\ValidateException;

class AuthServiceTest extends TestCase
{
    /**
     * @test Password must be at least 10 characters
     */
    public function testPasswordMinLength()
    {
        $this->expectException(ValidateException::class);
        $this->expectExceptionMessage('at least 10 characters');
        AuthService::validatePasswordComplexity('Short1!');
    }

    /**
     * @test Password must contain uppercase
     */
    public function testPasswordRequiresUppercase()
    {
        $this->expectException(ValidateException::class);
        $this->expectExceptionMessage('uppercase');
        AuthService::validatePasswordComplexity('lowercase123!');
    }

    /**
     * @test Password must contain lowercase
     */
    public function testPasswordRequiresLowercase()
    {
        $this->expectException(ValidateException::class);
        $this->expectExceptionMessage('lowercase');
        AuthService::validatePasswordComplexity('UPPERCASE123!');
    }

    /**
     * @test Password must contain digit
     */
    public function testPasswordRequiresDigit()
    {
        $this->expectException(ValidateException::class);
        $this->expectExceptionMessage('digit');
        AuthService::validatePasswordComplexity('NoDigitsHere!');
    }

    /**
     * @test Password must contain special character
     */
    public function testPasswordRequiresSpecialChar()
    {
        $this->expectException(ValidateException::class);
        $this->expectExceptionMessage('special character');
        AuthService::validatePasswordComplexity('NoSpecial123');
    }

    /**
     * @test Valid password passes all checks
     */
    public function testValidPasswordPasses()
    {
        // Should not throw
        AuthService::validatePasswordComplexity('ValidPass123!');
        $this->addToAssertionCount(1);
    }

    /**
     * @test Exactly 10 characters is valid
     */
    public function testBoundaryTenChars()
    {
        AuthService::validatePasswordComplexity('Abcdefgh1!');
        $this->addToAssertionCount(1);
    }

    /**
     * @test 9 characters is invalid
     */
    public function testBoundaryNineChars()
    {
        $this->expectException(ValidateException::class);
        AuthService::validatePasswordComplexity('Abcdegh1!');
    }
}

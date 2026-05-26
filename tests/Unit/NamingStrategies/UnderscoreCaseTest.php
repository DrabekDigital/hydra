<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\NamingStrategies;

use DrabekDigital\Hydra\NamingStrategies\UnderscoreCase;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class UnderscoreCaseTest extends TestCase
{
    private UnderscoreCase $strategy;

    protected function setUp(): void
    {
        $this->strategy = new UnderscoreCase();
    }

    // ── toInputKey ────────────────────────────────────────────────────

    public function testSingleWordIsLowercased(): void
    {
        $this->assertSame('name', $this->strategy->toInputKey('name'));
    }

    public function testAlreadyLowercasePassesThrough(): void
    {
        $this->assertSame('firstname', $this->strategy->toInputKey('firstname'));
    }

    public function testCamelCaseConvertsToSnakeCase(): void
    {
        $this->assertSame('first_name', $this->strategy->toInputKey('firstName'));
    }

    public function testMultipleWordsConverted(): void
    {
        $this->assertSame('created_at_timestamp', $this->strategy->toInputKey('createdAtTimestamp'));
    }

    public function testLeadingCapitalIsLowercased(): void
    {
        // "UserName" → "user_name", not "_user_name"
        $this->assertSame('user_name', $this->strategy->toInputKey('UserName'));
    }

    public function testAllUppercaseAcronymConverted(): void
    {
        // "URL" → "u_r_l" (each capital is its own word — this documents actual behaviour)
        $this->assertSame('u_r_l', $this->strategy->toInputKey('URL'));
    }

    public function testAcronymInMiddle(): void
    {
        // "parseHTMLContent" → "parse_h_t_m_l_content"
        $this->assertSame('parse_h_t_m_l_content', $this->strategy->toInputKey('parseHTMLContent'));
    }

    public function testSingleUppercaseLetter(): void
    {
        $this->assertSame('a', $this->strategy->toInputKey('A'));
    }

    public function testEmptyString(): void
    {
        $this->assertSame('', $this->strategy->toInputKey(''));
    }

    public function testNumbersArePreserved(): void
    {
        $this->assertSame('address2_line', $this->strategy->toInputKey('address2Line'));
    }

    // ── toOutputKey ───────────────────────────────────────────────────

    public function testInputAndOutputKeyAreSymmetric(): void
    {
        // Both directions use the same camelToSnake conversion
        $name = 'updatedAtMs';
        $this->assertSame(
            $this->strategy->toInputKey($name),
            $this->strategy->toOutputKey($name)
        );
    }

    public function testOutputKeyConvertsCorrectly(): void
    {
        $this->assertSame('my_property', $this->strategy->toOutputKey('myProperty'));
    }
}

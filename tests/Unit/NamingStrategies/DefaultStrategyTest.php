<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\NamingStrategies;

use DrabekDigital\Hydra\NamingStrategies\DefaultStrategy;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class DefaultStrategyTest extends TestCase
{
    public function testToInputKeyReturnsPropertyNameUnchanged(): void
    {
        $strategy = new DefaultStrategy();

        $this->assertSame('firstName', $strategy->toInputKey('firstName'));
        $this->assertSame('snake_case', $strategy->toInputKey('snake_case'));
    }

    public function testToOutputKeyReturnsPropertyNameUnchanged(): void
    {
        $strategy = new DefaultStrategy();

        $this->assertSame('firstName', $strategy->toOutputKey('firstName'));
        $this->assertSame('snake_case', $strategy->toOutputKey('snake_case'));
    }
}

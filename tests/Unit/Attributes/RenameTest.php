<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Attributes;

use DrabekDigital\Hydra\Attributes\Rename;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class RenameTest extends TestCase
{
    public function testFromOnlyIsValid(): void
    {
        $rename = new Rename(from: 'snake_name');
        $this->assertSame('snake_name', $rename->from);
        $this->assertNull($rename->to);
    }

    public function testToOnlyIsValid(): void
    {
        $rename = new Rename(to: 'output_key');
        $this->assertNull($rename->from);
        $this->assertSame('output_key', $rename->to);
    }

    public function testBothFromAndToIsValid(): void
    {
        $rename = new Rename(from: 'in', to: 'out');
        $this->assertSame('in', $rename->from);
        $this->assertSame('out', $rename->to);
    }

    public function testBothNullThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Rename(from: null, to: null);
    }

    public function testDefaultArgumentsThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Rename();
    }
}

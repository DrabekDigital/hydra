<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\CodeGen;

use DrabekDigital\Hydra\CodeGen\ArrayHydratorGenerator;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Car;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ArrayHydratorGeneratorTest extends TestCase
{
    public function testGenerateSimpleProducesStrictTypesAndHydrateMethod(): void
    {
        $generator = new ArrayHydratorGenerator();
        $code = $generator->generateSimple('SimpleClass');

        $this->assertStringContainsString('declare(strict_types=1);', $code);
        $this->assertStringContainsString('function hydrate(array $data, string $path = \'\'): array', $code);
        $this->assertStringNotContainsString('use SimpleClass;', $code);
    }

    public function testGenerateDiscriminatedProducesDiscriminatorSwitchCode(): void
    {
        $generator = new ArrayHydratorGenerator();
        $code = $generator->generateDiscriminated('type', [
            'car' => Car::class,
            'legacy' => \stdClass::class,
        ]);

        $this->assertStringContainsString("Missing discriminator \\'type\\'", $code);
        $this->assertStringContainsString("Unknown discriminator value for \\'type\\': ", $code);
        $this->assertStringContainsString('case \'car\':', $code);
    }

    public function testGenerateSimpleLoopThrowsWhenItemClassMissing(): void
    {
        $generator = new ArrayHydratorGenerator();
        $method = new \ReflectionMethod($generator, 'generateSimpleLoop');

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($generator, null);
    }
}

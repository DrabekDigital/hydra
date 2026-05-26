<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Attributes\CustomFormatter;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\AllowExtraKeysClaimDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\NamingStrategyClaimDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\RenameBothDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\RenameClaimDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\RenameFromOnlyDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\RenameToOnlyDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\SimpleDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\SkipClaimDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\SkipDehydrationOnlyDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\SkipHydrationOnlyDTO;
use DrabekDigital\Hydra\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class DeclaredFeatureSetCoverageTest extends TestCase
{
    public function testNamingStrategyAttributeIsAppliedForHydrationAndDehydration(): void
    {
        $dto = $this->hydrator->hydrate(
            ['first_name' => 'Jan'],
            NamingStrategyClaimDTO::class
        );
        $this->assertSame('Jan', $dto->firstName);

        $result = $this->dehydrator->dehydrate($dto);
        $this->assertArrayHasKey('first_name', $result);
        $this->assertSame('Jan', $result['first_name']);
    }

    public function testRenameAttributeIsAppliedForHydrationAndDehydration(): void
    {
        $dto = $this->hydrator->hydrate(['first_name' => 'Jan'], RenameClaimDTO::class);
        $this->assertSame('Jan', $dto->firstName);

        $result = $this->dehydrator->dehydrate($dto);
        $this->assertArrayHasKey('first_name', $result);
        $this->assertSame('Jan', $result['first_name']);
    }

    public function testRenameFromOnlyChangesInputKeyButLeavesOutputKeyAsPropertyName(): void
    {
        $dto = $this->hydrator->hydrate(['api_name' => 'Jan'], RenameFromOnlyDTO::class);
        $this->assertSame('Jan', $dto->firstName);

        $result = $this->dehydrator->dehydrate($dto);
        $this->assertArrayHasKey('firstName', $result); // output key = property name
        $this->assertArrayNotHasKey('api_name', $result);
        $this->assertSame('Jan', $result['firstName']);
    }

    public function testRenameToOnlyChangesOutputKeyButLeavesInputKeyAsPropertyName(): void
    {
        $dto = $this->hydrator->hydrate(['firstName' => 'Jan'], RenameToOnlyDTO::class);
        $this->assertSame('Jan', $dto->firstName);

        $result = $this->dehydrator->dehydrate($dto);
        $this->assertArrayHasKey('output_name', $result); // output key = renamed
        $this->assertArrayNotHasKey('firstName', $result);
        $this->assertSame('Jan', $result['output_name']);
    }

    public function testRenameBothUsesIndependentInputAndOutputKeys(): void
    {
        $dto = $this->hydrator->hydrate(['input_key' => 'Jan'], RenameBothDTO::class);
        $this->assertSame('Jan', $dto->firstName);

        $result = $this->dehydrator->dehydrate($dto);
        $this->assertArrayHasKey('output_key', $result);
        $this->assertArrayNotHasKey('input_key', $result);
        $this->assertSame('Jan', $result['output_key']);
    }

    public function testSkipAttributeIsAppliedForHydrationAndDehydration(): void
    {
        $dto = $this->hydrator->hydrate(['name' => 'Jan', 'visible' => 'x'], SkipClaimDTO::class);
        $this->assertNull($dto->name);
        $this->assertSame('x', $dto->visible);

        $result = $this->dehydrator->dehydrate($dto);
        $this->assertArrayNotHasKey('name', $result);
        $this->assertSame('x', $result['visible']);
    }

    public function testSkipHydrationOnlyKeepsPropertyAtDefaultButDehydratesIt(): void
    {
        $dto = $this->hydrator->hydrate(['internal' => 'secret', 'visible' => 'ok'], SkipHydrationOnlyDTO::class);
        $this->assertNull($dto->internal); // input value ignored
        $this->assertSame('ok', $dto->visible);

        $result = $this->dehydrator->dehydrate($dto);
        $this->assertArrayHasKey('internal', $result); // dehydration NOT skipped
        $this->assertNull($result['internal']);
    }

    public function testSkipDehydrationOnlyHydratesButOmitsFromOutput(): void
    {
        $dto = $this->hydrator->hydrate(['secret' => 'hidden', 'visible' => 'shown'], SkipDehydrationOnlyDTO::class);
        $this->assertSame('hidden', $dto->secret); // hydrated normally

        $result = $this->dehydrator->dehydrate($dto);
        $this->assertArrayNotHasKey('secret', $result); // dehydration skipped
        $this->assertSame('shown', $result['visible']);
    }

    public function testAllowExtraKeysAttributeAllowsExtraKeysWhenDefaultRejectsThem(): void
    {
        $this->assertHydrationFails(
            ['name' => 'John', 'age' => 30, 'ignored' => 'x'],
            SimpleDTO::class,
            'unexpected_key',
            'ignored'
        );

        $withAttribute = $this->hydrator->hydrate(
            ['name' => 'John', 'ignored' => 'x'],
            AllowExtraKeysClaimDTO::class
        );

        $this->assertSame('John', $withAttribute->name);
    }

    public function testCustomFormatterAttributeAcceptsClosureArguments(): void
    {
        $reflection = new \ReflectionClass(CustomFormatter::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);
        $firstParameter = $constructor->getParameters()[0];
        $type = $firstParameter->getType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertSame(\Closure::class, $type->getName());
    }

    public function testDateTimeRangeValidatorClassExists(): void
    {
        $this->assertTrue(class_exists('DrabekDigital\Hydra\Attributes\Validation\DateTimeRange'));
    }
}

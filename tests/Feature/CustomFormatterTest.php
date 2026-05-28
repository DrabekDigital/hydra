<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Exceptions\HydrationFailure;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\FailingFormatterWithHydrationErrorDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\WorkingFormatterDTO;
use DrabekDigital\Hydra\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class CustomFormatterTest extends TestCase
{
    public function testHydrateClosureTransformsInputValue(): void
    {
        $dto = $this->hydrator->hydrate(
            ['both' => 'hello', 'hydrateOnly' => 'hello', 'dehydrateOnly' => 'hello'],
            WorkingFormatterDTO::class
        );

        $this->assertSame('HELLO', $dto->both);
    }

    public function testDehydrateClosureTransformsOutputValue(): void
    {
        $dto = new WorkingFormatterDTO(both: 'HELLO', hydrateOnly: 'X', dehydrateOnly: 'WORLD');
        $result = $this->dehydrator->dehydrate($dto);

        $this->assertSame('hello', $result['both']);
    }

    public function testBothClosuresAppliedIndependentlyInRoundtrip(): void
    {
        $dto = $this->hydrator->hydrate(
            ['both' => 'hello', 'hydrateOnly' => 'hi', 'dehydrateOnly' => 'world'],
            WorkingFormatterDTO::class
        );

        $this->assertSame('HELLO', $dto->both);

        $result = $this->dehydrator->dehydrate($dto);

        $this->assertSame('hello', $result['both']);
    }

    public function testHydrateOnlyClosureDoesNotAffectDehydration(): void
    {
        $dto = $this->hydrator->hydrate(
            ['both' => 'x', 'hydrateOnly' => 'hello', 'dehydrateOnly' => 'y'],
            WorkingFormatterDTO::class
        );

        $this->assertSame('HELLO', $dto->hydrateOnly);

        $result = $this->dehydrator->dehydrate($dto);

        $this->assertSame('HELLO', $result['hydrateOnly']); // no dehydrate closure → raw value
    }

    public function testDehydrateOnlyClosureDoesNotAffectHydration(): void
    {
        $dto = $this->hydrator->hydrate(
            ['both' => 'x', 'hydrateOnly' => 'y', 'dehydrateOnly' => 'WORLD'],
            WorkingFormatterDTO::class
        );

        $this->assertSame('WORLD', $dto->dehydrateOnly); // no hydrate closure → raw value

        $result = $this->dehydrator->dehydrate($dto);

        $this->assertSame('world', $result['dehydrateOnly']); // dehydrate closure applied
    }

    public function testFormatterExceptionCanProvidePopulatedHydrationError(): void
    {
        try {
            $this->hydrator->hydrate(
                ['value' => 'hello', 'other' => 'ok'],
                FailingFormatterWithHydrationErrorDTO::class
            );
            $this->fail('Expected HydrationFailure was not thrown');
        } catch (HydrationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertSame('invalid_value', $errors[0]->code->value);
            $this->assertSame('value', $errors[0]->key);
            $this->assertSame('value', $errors[0]->fullPath);
            $this->assertStringContainsString('Formatter hydration mismatch', $errors[0]->programmerMessage);
        }
    }
}

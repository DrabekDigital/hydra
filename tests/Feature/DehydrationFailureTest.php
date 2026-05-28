<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Exceptions\DehydrationFailure;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ArrayAndMapDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\DiscriminatedGarageDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\FailingFormatterDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\FailingMapperDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\MapperExceptionDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\NoConstructorDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ScalarListDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Car;
use DrabekDigital\Hydra\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class DehydrationFailureTest extends TestCase
{
    // =========================================================================
    // Uninitialized property-only class
    // =========================================================================

    public function testUninitializedPropertyThrowsDehydrationFailure(): void
    {
        $dto = new NoConstructorDTO();

        $this->expectException(DehydrationFailure::class);
        $this->dehydrator->dehydrate($dto);
    }

    public function testUninitializedPropertyCollectsAllErrors(): void
    {
        $dto = new NoConstructorDTO();
        // title and count have no default — both must appear in errors
        // description and active have defaults — they don't fail

        try {
            $this->dehydrator->dehydrate($dto);
            $this->fail('Expected DehydrationFailure was not thrown');
        } catch (DehydrationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(2, $errors);

            $codes = array_map(fn ($err) => $err->code->value, $errors);
            $this->assertContains('unexpected_null', $codes);

            $keys = array_map(fn ($err) => $err->key, $errors);
            $this->assertContains('title', $keys);
            $this->assertContains('count', $keys);
        }
    }

    public function testUninitializedPropertyErrorCodeIsUnexpectedNull(): void
    {
        $dto = new NoConstructorDTO();

        $this->assertDehydrationFails($dto, 'unexpected_null', 'title');
    }

    public function testPartiallyInitializedPropertyOnlyClassSucceeds(): void
    {
        $dto = new NoConstructorDTO();
        $dto->title = 'Hello';
        $dto->count = 42;

        // description and active have defaults so omitting them is fine
        $result = $this->dehydrator->dehydrate($dto);

        $this->assertSame('Hello', $result['title']);
        $this->assertSame(42, $result['count']);
        $this->assertNull($result['description']);
        $this->assertTrue($result['active']);
    }

    // =========================================================================
    // Custom mapper failure
    // =========================================================================

    public function testFailingMapperThrowsDehydrationFailure(): void
    {
        $dto = new FailingMapperDTO(value: 'anything', other: 'ok');

        $this->expectException(DehydrationFailure::class);
        $this->dehydrator->dehydrate($dto);
    }

    public function testFailingMapperErrorCodeIsInvalidValue(): void
    {
        $dto = new FailingMapperDTO(value: 'anything', other: 'ok');

        $this->assertDehydrationFails($dto, 'invalid_value', 'value');
    }

    public function testFailingMapperDoesNotPreventCollectingOtherFields(): void
    {
        $dto = new FailingMapperDTO(value: 'anything', other: 'ok');

        try {
            $this->dehydrator->dehydrate($dto);
            $this->fail('Expected DehydrationFailure was not thrown');
        } catch (DehydrationFailure $e) {
            $errors = $e->getErrors();
            // Only the mapper field fails; 'other' dehydrates fine so only 1 error
            $this->assertCount(1, $errors);
            $this->assertSame('invalid_value', $errors[0]->code->value);
            $this->assertSame('value', $errors[0]->key);
        }
    }

    // =========================================================================
    // MapperException — controlled mapper failure
    // =========================================================================

    public function testMapperExceptionResultsInDehydrationFailure(): void
    {
        $dto = new MapperExceptionDTO(value: 'anything', other: 'ok');

        $this->expectException(DehydrationFailure::class);
        $this->dehydrator->dehydrate($dto);
    }

    public function testMapperExceptionProducesInvalidValueError(): void
    {
        $dto = new MapperExceptionDTO(value: 'anything', other: 'ok');

        $this->assertDehydrationFails($dto, 'invalid_value', 'value');
    }

    public function testMapperExceptionMessageIsPreserved(): void
    {
        $dto = new MapperExceptionDTO(value: 'anything', other: 'ok');

        try {
            $this->dehydrator->dehydrate($dto);
            $this->fail('Expected DehydrationFailure was not thrown');
        } catch (DehydrationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertStringContainsString('intentionally failed', $errors[0]->programmerMessage);
        }
    }

    // =========================================================================
    // Custom formatter failure
    // =========================================================================

    public function testFailingFormatterThrowsDehydrationFailure(): void
    {
        $dto = new FailingFormatterDTO(value: 'hello', other: 'ok');

        $this->expectException(DehydrationFailure::class);
        $this->dehydrator->dehydrate($dto);
    }

    public function testFailingFormatterErrorCodeIsInvalidValue(): void
    {
        $dto = new FailingFormatterDTO(value: 'hello', other: 'ok');

        $this->assertDehydrationFails($dto, 'invalid_value', 'value');
    }

    public function testFailingFormatterDoesNotPreventCollectingOtherFields(): void
    {
        $dto = new FailingFormatterDTO(value: 'hello', other: 'ok');

        try {
            $this->dehydrator->dehydrate($dto);
            $this->fail('Expected DehydrationFailure was not thrown');
        } catch (DehydrationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertSame('invalid_value', $errors[0]->code->value);
            $this->assertSame('value', $errors[0]->key);
        }
    }

    public function testRuntimeTypeErrorIsWrappedInDehydrationFailure(): void
    {
        $reflection = new \ReflectionClass(DiscriminatedGarageDTO::class);
        $dto = $reflection->newInstanceArgs([
            new Car('Skoda', 2024, 5),
            [123],
        ]);
        $this->assertInstanceOf(DiscriminatedGarageDTO::class, $dto);

        try {
            $this->dehydrator->dehydrate($dto);
            $this->fail('Expected DehydrationFailure was not thrown');
        } catch (DehydrationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertSame('type_mismatch', $errors[0]->code->value);
            $this->assertNull($errors[0]->key);
            $this->assertStringContainsString('get_class', $errors[0]->programmerMessage);
        }
    }

    public function testListOfScalarItemMismatchFailsDuringDehydration(): void
    {
        $reflection = new \ReflectionClass(ScalarListDTO::class);
        $dto = $reflection->newInstanceArgs([
            ['foo', 1],
            [1, 2, 3],
        ]);
        $this->assertInstanceOf(ScalarListDTO::class, $dto);

        try {
            $this->dehydrator->dehydrate($dto);
            $this->fail('Expected DehydrationFailure was not thrown');
        } catch (DehydrationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertSame('type_mismatch', $errors[0]->code->value);
            $this->assertSame(1, $errors[0]->key);
            $this->assertSame('strings[1]', $errors[0]->fullPath);
        }
    }

    public function testArrayOfScalarItemMismatchFailsDuringDehydration(): void
    {
        $reflection = new \ReflectionClass(ArrayAndMapDTO::class);
        $dto = $reflection->newInstanceArgs([
            ['foo', 1],
            ['a' => 10, 'b' => 20],
        ]);
        $this->assertInstanceOf(ArrayAndMapDTO::class, $dto);

        try {
            $this->dehydrator->dehydrate($dto);
            $this->fail('Expected DehydrationFailure was not thrown');
        } catch (DehydrationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertSame('type_mismatch', $errors[0]->code->value);
            $this->assertSame(1, $errors[0]->key);
            $this->assertSame('tags[1]', $errors[0]->fullPath);
        }
    }

    public function testMapOfScalarValueMismatchFailsDuringDehydration(): void
    {
        $reflection = new \ReflectionClass(ArrayAndMapDTO::class);
        $dto = $reflection->newInstanceArgs([
            ['foo', 'bar'],
            ['a' => 10, 'b' => 'oops'],
        ]);
        $this->assertInstanceOf(ArrayAndMapDTO::class, $dto);

        try {
            $this->dehydrator->dehydrate($dto);
            $this->fail('Expected DehydrationFailure was not thrown');
        } catch (DehydrationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertSame('type_mismatch', $errors[0]->code->value);
            $this->assertSame('b', $errors[0]->key);
            $this->assertSame('scores.b', $errors[0]->fullPath);
        }
    }
}

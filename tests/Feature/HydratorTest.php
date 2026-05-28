<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Exceptions\HydrationFailure;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\AddressDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\AllScalarsDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ArrayAndMapDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ArticleDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\AttributeDefaultDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\CompanyDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ConstructorTypeErrorDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\DefaultValueDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\MixedDefaultsDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\NullableDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ObjectMapDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\PersonDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ScalarListDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\SimpleDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\TagDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\UndefinableDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\UnionScalarDTO;
use DrabekDigital\Hydra\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class HydratorTest extends TestCase
{
    // =========================================================================
    // Basic Scalar Types
    // =========================================================================

    public function testHydratesSimpleDto(): void
    {
        $this->assertHydrates(
            ['name' => 'John', 'age' => 30],
            SimpleDTO::class,
            function (SimpleDTO $dto): void {
                $this->assertEquals('John', $dto->name);
                $this->assertEquals(30, $dto->age);
            }
        );
    }

    public function testHydratesAllScalarTypes(): void
    {
        $data = [
            'string' => 'hello',
            'int' => 42,
            'float' => 3.14,
            'bool' => true,
        ];

        $this->assertHydrates($data, AllScalarsDTO::class, function (AllScalarsDTO $dto) {
            $this->assertSame('hello', $dto->string);
            $this->assertSame(42, $dto->int);
            $this->assertSame(3.14, $dto->float);
            $this->assertSame(true, $dto->bool);
        });
    }

    public function testHydratesArrayOfAndMapOfAttributes(): void
    {
        $this->assertHydrates(
            [
                'tags' => ['php', 'hydrator'],
                'scores' => ['a' => 10, 'b' => 20],
            ],
            ArrayAndMapDTO::class,
            function (ArrayAndMapDTO $dto): void {
                $this->assertSame(['php', 'hydrator'], $dto->tags);
                $this->assertSame(['a' => 10, 'b' => 20], $dto->scores);
            }
        );
    }

    public function testHydratesMapOfWithObjectValues(): void
    {
        $this->assertHydrates(
            [
                'addresses' => [
                    'home' => ['street' => '1 Main St', 'city' => 'Springfield', 'zip' => '12345'],
                    'work' => ['street' => '2 Corp Ave', 'city' => 'Business City', 'zip' => '99999'],
                ],
            ],
            ObjectMapDTO::class,
            function (ObjectMapDTO $dto): void {
                $this->assertCount(2, $dto->addresses);
                $this->assertInstanceOf(AddressDTO::class, $dto->addresses['home']);
                $this->assertSame('1 Main St', $dto->addresses['home']->street);
                $this->assertSame('Springfield', $dto->addresses['home']->city);
                $this->assertInstanceOf(AddressDTO::class, $dto->addresses['work']);
                $this->assertSame('2 Corp Ave', $dto->addresses['work']->street);
            }
        );
    }

    public function testHydratesNullableProperties(): void
    {
        // With values
        $this->assertHydrates(
            ['name' => 'John', 'age' => 30],
            NullableDTO::class,
            fn (NullableDTO $dto) => $this->assertSame('John', $dto->name)
        );

        // With nulls
        $this->assertHydrates(
            ['name' => null, 'age' => null],
            NullableDTO::class,
            function (NullableDTO $dto): void {
                $this->assertNull($dto->name);
                $this->assertNull($dto->age);
            }
        );
    }

    public function testHydratesUnionScalarTypes(): void
    {
        // int|float with int
        $this->assertHydrates(
            ['number' => 42, 'identifier' => 'abc'],
            UnionScalarDTO::class,
            fn (UnionScalarDTO $dto) => $this->assertSame(42, $dto->number)
        );

        // int|float with float
        $this->assertHydrates(
            ['number' => 3.14, 'identifier' => 123],
            UnionScalarDTO::class,
            fn (UnionScalarDTO $dto) => $this->assertSame(3.14, $dto->number)
        );
    }

    // =========================================================================
    // Default Values
    // =========================================================================

    public function testUsesParameterDefaultWhenKeyMissing(): void
    {
        $this->assertHydrates(
            ['name' => 'John'],
            DefaultValueDTO::class,
            function (DefaultValueDTO $dto) {
                $this->assertSame('John', $dto->name);
                $this->assertSame('active', $dto->status);
                $this->assertSame(0, $dto->count);
            }
        );
    }

    public function testUsesAttributeDefaultWhenKeyMissing(): void
    {
        $this->assertHydrates(
            ['name' => 'John'],
            AttributeDefaultDTO::class,
            function (AttributeDefaultDTO $dto) {
                $this->assertSame('John', $dto->name);
                $this->assertSame('pending', $dto->status);
            }
        );
    }

    public function testProvidedValueOverridesDefault(): void
    {
        $this->assertHydrates(
            ['name' => 'John', 'status' => 'custom'],
            AttributeDefaultDTO::class,
            fn (AttributeDefaultDTO $dto) => $this->assertSame('custom', $dto->status)
        );
    }

    // =========================================================================
    // Undefinable Properties
    // =========================================================================

    public function testUndefinablePropertyRemainsUninitializedWhenMissing(): void
    {
        $dto = $this->hydrator->hydrate(['name' => 'John'], UndefinableDTO::class);

        $this->assertSame('John', $dto->name);

        $reflection = new \ReflectionProperty($dto, 'optional');
        $this->assertFalse($reflection->isInitialized($dto));
    }

    public function testUndefinablePropertyIsSetWhenProvided(): void
    {
        $dto = $this->hydrator->hydrate(
            ['name' => 'John', 'optional' => 'value'],
            UndefinableDTO::class
        );

        $this->assertSame('value', $dto->optional);
    }

    public function testUndefinablePropertyAcceptsNull(): void
    {
        $dto = $this->hydrator->hydrate(
            ['name' => 'John', 'optional' => null],
            UndefinableDTO::class
        );

        $this->assertNull($dto->optional);
    }

    public function testMixedDefaultsAndUndefinable(): void
    {
        $dto = $this->hydrator->hydrate(
            ['required' => 'value'],
            MixedDefaultsDTO::class
        );

        $this->assertSame('value', $dto->required);
        $this->assertSame('attr_default', $dto->attrDefault);
        $this->assertSame('param_default', $dto->paramDefault);

        $ref = new \ReflectionProperty($dto, 'undefinable');
        $this->assertFalse($ref->isInitialized($dto));
    }

    // =========================================================================
    // Nested Objects
    // =========================================================================

    public function testHydratesNestedObject(): void
    {
        $data = [
            'name' => 'John',
            'address' => [
                'street' => '123 Main St',
                'city' => 'Springfield',
                'zip' => '12345',
            ],
        ];

        $this->assertHydrates($data, PersonDTO::class, function (PersonDTO $dto) {
            $this->assertSame('John', $dto->name);
            $this->assertInstanceOf(AddressDTO::class, $dto->address);
            $this->assertSame('123 Main St', $dto->address->street);
            $this->assertSame('Springfield', $dto->address->city);
        });
    }

    public function testHydratesMultipleNestedObjects(): void
    {
        $data = [
            'name' => 'Acme Corp',
            'headquarters' => [
                'street' => '100 HQ Blvd',
                'city' => 'Business City',
                'zip' => '00001',
            ],
            'mailingAddress' => [
                'street' => 'PO Box 100',
                'city' => 'Mail Town',
                'zip' => '99999',
            ],
        ];

        $this->assertHydrates($data, CompanyDTO::class, function (CompanyDTO $dto) {
            $this->assertSame('Acme Corp', $dto->name);
            $this->assertSame('100 HQ Blvd', $dto->headquarters->street);
            $this->assertNotNull($dto->mailingAddress);
            $this->assertSame('PO Box 100', $dto->mailingAddress->street);
        });
    }

    public function testHydratesNullableNestedObjectAsNull(): void
    {
        $data = [
            'name' => 'Acme Corp',
            'headquarters' => [
                'street' => '100 HQ Blvd',
                'city' => 'Business City',
                'zip' => '00001',
            ],
            'mailingAddress' => null,
        ];

        $this->assertHydrates($data, CompanyDTO::class, function (CompanyDTO $dto) {
            $this->assertNull($dto->mailingAddress);
        });
    }

    // =========================================================================
    // Lists
    // =========================================================================

    public function testHydratesListOfObjects(): void
    {
        $data = [
            'title' => 'My Article',
            'tags' => [
                ['name' => 'PHP', 'slug' => 'php'],
                ['name' => 'Testing', 'slug' => 'testing'],
            ],
        ];

        $this->assertHydrates($data, ArticleDTO::class, function (ArticleDTO $dto) {
            $this->assertSame('My Article', $dto->title);
            $this->assertCount(2, $dto->tags);
            $this->assertInstanceOf(TagDTO::class, $dto->tags[0]);
            $this->assertSame('PHP', $dto->tags[0]->name);
            $this->assertSame('testing', $dto->tags[1]->slug);
        });
    }

    public function testHydratesEmptyList(): void
    {
        $data = [
            'title' => 'Empty Article',
            'tags' => [],
        ];

        $this->assertHydrates($data, ArticleDTO::class, function (ArticleDTO $dto) {
            $this->assertCount(0, $dto->tags);
            $this->assertSame([], $dto->tags);
        });
    }

    public function testHydratesListOfScalars(): void
    {
        $data = [
            'strings' => ['a', 'b', 'c'],
            'ints' => [1, 2, 3],
        ];

        $this->assertHydrates($data, ScalarListDTO::class, function (ScalarListDTO $dto) {
            $this->assertSame(['a', 'b', 'c'], $dto->strings);
            $this->assertSame([1, 2, 3], $dto->ints);
        });
    }

    public function testListOfScalarsFailsOnInvalidItemType(): void
    {
        try {
            $this->hydrator->hydrate(
                ['strings' => ['foo', 1], 'ints' => [1, 2, 3]],
                ScalarListDTO::class
            );
            $this->fail('Expected HydrationFailure was not thrown');
        } catch (HydrationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertSame('type_mismatch', $errors[0]->code->value);
            $this->assertSame(1, $errors[0]->key);
            $this->assertSame('strings[1]', $errors[0]->fullPath);
        }
    }

    public function testArrayOfScalarsFailsOnInvalidItemType(): void
    {
        try {
            $this->hydrator->hydrate(
                ['tags' => ['foo', 1], 'scores' => ['a' => 10, 'b' => 20]],
                ArrayAndMapDTO::class
            );
            $this->fail('Expected HydrationFailure was not thrown');
        } catch (HydrationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertSame('type_mismatch', $errors[0]->code->value);
            $this->assertSame(1, $errors[0]->key);
            $this->assertSame('tags[1]', $errors[0]->fullPath);
        }
    }

    public function testMapOfScalarsFailsOnInvalidValueType(): void
    {
        try {
            $this->hydrator->hydrate(
                ['tags' => ['php', 'hydrator'], 'scores' => ['a' => 10, 'b' => 'oops']],
                ArrayAndMapDTO::class
            );
            $this->fail('Expected HydrationFailure was not thrown');
        } catch (HydrationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertSame('type_mismatch', $errors[0]->code->value);
            $this->assertSame('b', $errors[0]->key);
            $this->assertSame('scores.b', $errors[0]->fullPath);
        }
    }

    // =========================================================================
    // Error Cases
    // =========================================================================

    public function testFailsOnMissingRequiredKey(): void
    {
        $this->assertHydrationFails(
            ['name' => 'John'], // missing 'age'
            SimpleDTO::class,
            'missing_key',
            'age'
        );
    }

    public function testFailsOnTypeMismatch(): void
    {
        $this->assertHydrationFails(
            ['name' => 'John', 'age' => 'not-an-int'],
            SimpleDTO::class,
            'type_mismatch',
            'age'
        );
    }

    public function testFailsOnInvalidNestedObject(): void
    {
        try {
            $this->hydrator->hydrate(
                [
                    'name' => 'John',
                    'address' => 'not-an-array',
                ],
                PersonDTO::class
            );
            $this->fail('Expected HydrationFailure was not thrown');
        } catch (HydrationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertSame('type_mismatch', $errors[0]->code->value);
            $this->assertSame('address', $errors[0]->key);
        }
    }

    public function testConstructorTypeErrorIsWrappedInHydrationFailure(): void
    {
        try {
            $this->hydrator->hydrate(['name' => 'John'], ConstructorTypeErrorDTO::class);
            $this->fail('Expected HydrationFailure was not thrown');
        } catch (HydrationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertSame('type_mismatch', $errors[0]->code->value);
            $this->assertNull($errors[0]->key);
            $this->assertStringContainsString('Simulated constructor type error', $errors[0]->programmerMessage);
        }
    }

    public function testFailsOnMissingNestedKey(): void
    {
        $this->assertHydrationFails(
            [
                'name' => 'John',
                'address' => [
                    'street' => '123 Main St',
                    // missing 'city' and 'zip'
                ],
            ],
            PersonDTO::class,
            'missing_key',
            'city'
        );
    }

    // =========================================================================
    // Bypass Cache
    // =========================================================================

    public function testBypassCacheRegeneratesHydrator(): void
    {
        // First hydration - creates cache
        $dto1 = $this->hydrator->hydrate(['name' => 'John', 'age' => 30], SimpleDTO::class);

        // Get cache file count
        $cacheFiles = glob(self::$cacheDir.'/hydrator/*.php') ?: [];
        $initialCount = count($cacheFiles);

        // Second hydration with bypassCache - should still work
        $dto2 = $this->hydrator->hydrate(
            ['name' => 'Jane', 'age' => 25],
            SimpleDTO::class,
            bypassCache: true
        );

        $this->assertSame('Jane', $dto2->name);
        $this->assertSame(25, $dto2->age);
    }
}

<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Tests\Fixtures\Dto\AddressDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\AllScalarsDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ArrayAndMapDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ArticleDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\CompanyDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\DateTimeDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\EnumDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\NullableDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ObjectMapDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\PersonDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ScalarListDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\SimpleDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\TagDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\UndefinableAsNullDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\UndefinableDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\UndefinableWithFallbackDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Priority;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Status;
use DrabekDigital\Hydra\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class DehydratorTest extends TestCase
{
    // =========================================================================
    // Basic Scalar Types
    // =========================================================================

    public function testDehydratesSimpleDto(): void
    {
        $dto = new SimpleDTO('John', 30);

        $this->assertDehydrates($dto, [
            'name' => 'John',
            'age' => 30,
        ]);
    }

    public function testDehydratesAllScalarTypes(): void
    {
        $dto = new AllScalarsDTO('hello', 42, 3.14, true);

        $this->assertDehydrates($dto, [
            'string' => 'hello',
            'int' => 42,
            'float' => 3.14,
            'bool' => true,
        ]);
    }

    public function testDehydratesNullableWithValues(): void
    {
        $dto = new NullableDTO('John', 30);

        $this->assertDehydrates($dto, [
            'name' => 'John',
            'age' => 30,
        ]);
    }

    public function testDehydratesNullableWithNulls(): void
    {
        $dto = new NullableDTO(null, null);

        $this->assertDehydrates($dto, [
            'name' => null,
            'age' => null,
        ]);
    }

    // =========================================================================
    // Nested Objects
    // =========================================================================

    public function testDehydratesNestedObject(): void
    {
        $dto = new PersonDTO(
            'John',
            new AddressDTO('123 Main St', 'Springfield', '12345')
        );

        $this->assertDehydrates($dto, [
            'name' => 'John',
            'address' => [
                'street' => '123 Main St',
                'city' => 'Springfield',
                'zip' => '12345',
            ],
        ]);
    }

    public function testDehydratesMultipleNestedObjects(): void
    {
        $dto = new CompanyDTO(
            'Acme Corp',
            new AddressDTO('100 HQ Blvd', 'Business City', '00001'),
            new AddressDTO('PO Box 100', 'Mail Town', '99999')
        );

        $this->assertDehydrates($dto, [
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
        ]);
    }

    public function testDehydratesNullableNestedAsNull(): void
    {
        $dto = new CompanyDTO(
            'Acme Corp',
            new AddressDTO('100 HQ Blvd', 'Business City', '00001'),
            null
        );

        $this->assertDehydrates($dto, [
            'name' => 'Acme Corp',
            'headquarters' => [
                'street' => '100 HQ Blvd',
                'city' => 'Business City',
                'zip' => '00001',
            ],
            'mailingAddress' => null,
        ]);
    }

    // =========================================================================
    // Lists
    // =========================================================================

    public function testDehydratesListOfObjects(): void
    {
        $dto = new ArticleDTO(
            'My Article',
            [
                new TagDTO('PHP', 'php'),
                new TagDTO('Testing', 'testing'),
            ]
        );

        $this->assertDehydrates($dto, [
            'title' => 'My Article',
            'tags' => [
                ['name' => 'PHP', 'slug' => 'php'],
                ['name' => 'Testing', 'slug' => 'testing'],
            ],
        ]);
    }

    public function testDehydratesEmptyList(): void
    {
        $dto = new ArticleDTO('Empty Article', []);

        $this->assertDehydrates($dto, [
            'title' => 'Empty Article',
            'tags' => [],
        ]);
    }

    public function testDehydratesListOfScalars(): void
    {
        $dto = new ScalarListDTO(['a', 'b', 'c'], [1, 2, 3]);

        $this->assertDehydrates($dto, [
            'strings' => ['a', 'b', 'c'],
            'ints' => [1, 2, 3],
        ]);
    }

    public function testDehydratesArrayOfAndMapOfAttributes(): void
    {
        $dto = new ArrayAndMapDTO(
            tags: ['php', 'hydrator'],
            scores: ['a' => 10, 'b' => 20]
        );

        $this->assertDehydrates($dto, [
            'tags' => ['php', 'hydrator'],
            'scores' => ['a' => 10, 'b' => 20],
        ]);
    }

    public function testDehydratesMapOfScalarsExplicitly(): void
    {
        $dto = new ArrayAndMapDTO(
            tags: ['php'],
            scores: ['alpha' => 100, 'beta' => 200]
        );

        $this->assertDehydrates($dto, [
            'tags' => ['php'],
            'scores' => ['alpha' => 100, 'beta' => 200],
        ]);
    }

    public function testDehydratesMapOfWithObjectValues(): void
    {
        $dto = new ObjectMapDTO(
            addresses: [
                'home' => new AddressDTO('1 Main St', 'Springfield', '12345'),
                'work' => new AddressDTO('2 Corp Ave', 'Business City', '99999'),
            ]
        );

        $this->assertDehydrates($dto, [
            'addresses' => [
                'home' => ['street' => '1 Main St', 'city' => 'Springfield', 'zip' => '12345'],
                'work' => ['street' => '2 Corp Ave', 'city' => 'Business City', 'zip' => '99999'],
            ],
        ]);
    }

    public function testDehydratesUndefinableSkipsKeyWhenMissing(): void
    {
        $dto = $this->hydrator->hydrate(
            ['name' => 'John'],
            UndefinableDTO::class
        );

        $result = $this->dehydrator->dehydrate($dto);
        $this->assertSame('John', $result['name']);
        $this->assertArrayNotHasKey('optional', $result);
    }

    public function testDehydratesUndefinableAsNullWhenMissing(): void
    {
        $dto = $this->hydrator->hydrate(
            ['name' => 'John'],
            UndefinableAsNullDTO::class
        );

        $result = $this->dehydrator->dehydrate($dto);
        $this->assertSame('John', $result['name']);
        $this->assertArrayHasKey('optional', $result);
        $this->assertNull($result['optional']);
    }

    public function testDehydratesUndefinableAsValueWhenMissing(): void
    {
        $dto = $this->hydrator->hydrate(
            ['name' => 'John'],
            UndefinableWithFallbackDTO::class
        );

        $result = $this->dehydrator->dehydrate($dto);
        $this->assertSame('John', $result['name']);
        $this->assertSame('missing', $result['optional']);
    }

    public function testDehydratesUndefinableAsNullWhenInitialized(): void
    {
        $dto = $this->hydrator->hydrate(
            ['name' => 'John', 'optional' => 'value'],
            UndefinableAsNullDTO::class
        );

        $result = $this->dehydrator->dehydrate($dto);
        $this->assertSame('John', $result['name']);
        $this->assertSame('value', $result['optional']);
    }

    public function testDehydratesUndefinableAsValueWhenInitialized(): void
    {
        $dto = $this->hydrator->hydrate(
            ['name' => 'John', 'optional' => 'value'],
            UndefinableWithFallbackDTO::class
        );

        $result = $this->dehydrator->dehydrate($dto);
        $this->assertSame('John', $result['name']);
        $this->assertSame('value', $result['optional']);
    }

    // =========================================================================
    // Enums
    // =========================================================================

    public function testDehydratesEnums(): void
    {
        $dto = new EnumDTO(Status::Published, Priority::High);

        $this->assertDehydrates($dto, [
            'status' => 'published',
            'priority' => 3,
        ]);
    }

    // =========================================================================
    // DateTime
    // =========================================================================

    public function testDehydratesDatetime(): void
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', '2024-06-15');
        $this->assertInstanceOf(\DateTimeImmutable::class, $date);

        $dto = new DateTimeDTO(
            'Event',
            $date
        );

        $this->assertDehydrates($dto, [
            'name' => 'Event',
            'date' => '2024-06-15',
        ]);
    }

    // =========================================================================
    // Roundtrip Tests
    // =========================================================================

    public function testRoundtripSimpleDto(): void
    {
        $this->assertRoundtrip(
            ['name' => 'John', 'age' => 30],
            SimpleDTO::class
        );
    }

    public function testRoundtripNestedDto(): void
    {
        $this->assertRoundtrip(
            [
                'name' => 'John',
                'address' => [
                    'street' => '123 Main St',
                    'city' => 'Springfield',
                    'zip' => '12345',
                ],
            ],
            PersonDTO::class
        );
    }

    public function testRoundtripListOfObjects(): void
    {
        $this->assertRoundtrip(
            [
                'title' => 'My Article',
                'tags' => [
                    ['name' => 'PHP', 'slug' => 'php'],
                    ['name' => 'Testing', 'slug' => 'testing'],
                ],
            ],
            ArticleDTO::class
        );
    }

    public function testRoundtripEnums(): void
    {
        $this->assertRoundtrip(
            ['status' => 'published', 'priority' => 2],
            EnumDTO::class
        );
    }

    public function testRoundtripDatetime(): void
    {
        $this->assertRoundtrip(
            ['name' => 'Event', 'date' => '2024-06-15'],
            DateTimeDTO::class
        );
    }

    // =========================================================================
    // Bypass Cache
    // =========================================================================

    public function testBypassCacheRegeneratesDehydrator(): void
    {
        $dto = new SimpleDTO('John', 30);

        // First dehydration - creates cache
        $result1 = $this->dehydrator->dehydrate($dto);

        // Second dehydration with bypassCache
        $result2 = $this->dehydrator->dehydrate($dto, bypassCache: true);

        $this->assertEquals($result1, $result2);
    }
}

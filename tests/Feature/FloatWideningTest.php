<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Tests\Fixtures\Dto\FloatWideningDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\FloatWideningPropertiesDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\UnionScalarDTO;
use DrabekDigital\Hydra\Tests\TestCase;

/**
 * JSON numbers without a fraction (e.g. `5`) decode to int; float targets must accept them.
 *
 * @internal
 *
 * @coversNothing
 */
class FloatWideningTest extends TestCase
{
    public function testWidensIntToFloatEverywhere(): void
    {
        $data = [
            'price' => 5,
            'discount' => 2,
            'samples' => [1, 2.5, 3],
            'rates' => ['czk' => 25, 'eur' => 1.0],
            'weights' => ['a' => 7, 'b' => 0.5],
            'optionalWeights' => [1, null, 2.5],
            'ratio' => 2,
            'bonus' => 10,
        ];

        $this->assertHydrates($data, FloatWideningDTO::class, function (FloatWideningDTO $dto): void {
            $this->assertSame(5.0, $dto->price);
            $this->assertSame(2.0, $dto->discount);
            $this->assertSame([1.0, 2.5, 3.0], $dto->samples);
            $this->assertSame(['czk' => 25.0, 'eur' => 1.0], $dto->rates);
            $this->assertSame(['a' => 7.0, 'b' => 0.5], $dto->weights);
            $this->assertSame([1.0, null, 2.5], $dto->optionalWeights);
            $this->assertSame(2.0, $dto->ratio);
            $this->assertSame(10.0, $dto->bonus);
        });
    }

    public function testWidensIntToFloatOnPublicProperties(): void
    {
        $this->assertHydrates(
            ['amount' => 100, 'tax' => 21],
            FloatWideningPropertiesDTO::class,
            function (FloatWideningPropertiesDTO $dto): void {
                $this->assertSame(100.0, $dto->amount);
                $this->assertSame(21.0, $dto->tax);
            }
        );
    }

    public function testIntFloatUnionKeepsInt(): void
    {
        $this->assertHydrates(
            ['number' => 42, 'identifier' => 'abc'],
            UnionScalarDTO::class,
            fn (UnionScalarDTO $dto) => $this->assertSame(42, $dto->number)
        );
    }

    public function testNumericStringIsStillRejected(): void
    {
        $this->assertHydrationFails($this->validPayload(['price' => '5']), FloatWideningDTO::class, 'type_mismatch', 'price');
    }

    public function testBoolIsStillRejected(): void
    {
        $this->assertHydrationFails($this->validPayload(['price' => true]), FloatWideningDTO::class, 'type_mismatch', 'price');
    }

    public function testNonNumericCollectionItemIsStillRejected(): void
    {
        $this->assertHydrationFails($this->validPayload(['samples' => [1, '2']]), FloatWideningDTO::class, 'type_mismatch');
        $this->assertHydrationFails($this->validPayload(['rates' => ['czk' => '25']]), FloatWideningDTO::class, 'type_mismatch', 'czk');
        $this->assertHydrationFails($this->validPayload(['weights' => ['a' => false]]), FloatWideningDTO::class, 'type_mismatch', 'a');
    }

    public function testRoundtripEmitsFloats(): void
    {
        $object = $this->hydrator->hydrate($this->validPayload(['price' => 5, 'samples' => [1]]), FloatWideningDTO::class);
        $result = $this->dehydrator->dehydrate($object);

        $this->assertSame(5.0, $result['price']);
        $this->assertSame([1.0], $result['samples']);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'price' => 1.5,
            'discount' => null,
            'samples' => [],
            'rates' => [],
            'weights' => [],
            'optionalWeights' => [],
            ...$overrides,
        ];
    }
}

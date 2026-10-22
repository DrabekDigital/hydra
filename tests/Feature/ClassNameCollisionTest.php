<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Exceptions\HydrationFailure;
use DrabekDigital\Hydra\Tests\Fixtures\Collisions\Billing\Address as BillingAddress;
use DrabekDigital\Hydra\Tests\Fixtures\Collisions\Billing\Kind as BillingKind;
use DrabekDigital\Hydra\Tests\Fixtures\Collisions\ConflictingShortNamesDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Collisions\DehydrationError;
use DrabekDigital\Hydra\Tests\Fixtures\Collisions\HydrationError;
use DrabekDigital\Hydra\Tests\Fixtures\Collisions\HydrationErrorCode;
use DrabekDigital\Hydra\Tests\Fixtures\Collisions\HydratorRuntime;
use DrabekDigital\Hydra\Tests\Fixtures\Collisions\Shipping\Address as ShippingAddress;
use DrabekDigital\Hydra\Tests\Fixtures\Collisions\Shipping\Kind as ShippingKind;
use DrabekDigital\Hydra\Tests\TestCase;

/**
 * Generated code must reference classes by fully qualified names, so classes sharing
 * a short name (with each other or with Hydra's own runtime classes) do not collide.
 *
 * @internal
 *
 * @coversNothing
 */
class ClassNameCollisionTest extends TestCase
{
    private const ADDRESS_MAPPING = [
        'billing' => BillingAddress::class,
        'shipping' => ShippingAddress::class,
    ];

    // =========================================================================
    // Classes sharing a short name across namespaces
    // =========================================================================

    public function testHydratesDtoWithSameShortNamedTypes(): void
    {
        $this->assertHydrates($this->conflictingShortNamesData(), ConflictingShortNamesDTO::class, function (ConflictingShortNamesDTO $dto) {
            $this->assertInstanceOf(BillingAddress::class, $dto->billing);
            $this->assertSame('VAT-1', $dto->billing->vatId);
            $this->assertInstanceOf(ShippingAddress::class, $dto->shipping);
            $this->assertSame('Jane', $dto->shipping->recipient);

            $this->assertCount(2, $dto->billingHistory);
            $this->assertInstanceOf(BillingAddress::class, $dto->billingHistory[1]);
            $this->assertSame('VAT-00', $dto->billingHistory[1]->vatId);
            $this->assertInstanceOf(ShippingAddress::class, $dto->shippingByName['home']);
            $this->assertSame('John', $dto->shippingByName['home']->recipient);

            $this->assertInstanceOf(ShippingAddress::class, $dto->primary);
            $this->assertInstanceOf(BillingAddress::class, $dto->addresses[0]);
            $this->assertInstanceOf(ShippingAddress::class, $dto->addresses[1]);
            $this->assertInstanceOf(BillingAddress::class, $dto->addressesByName['office']);

            $this->assertSame(BillingKind::Receipt, $dto->billingKind);
            $this->assertSame(ShippingKind::Express, $dto->shippingKind);
            $this->assertSame('billing:B-7', $dto->billingLabel);
            $this->assertSame('shipping:S-9', $dto->shippingLabel);
        });
    }

    public function testHydratesDefaultEnumOfSameShortNamedType(): void
    {
        $data = $this->conflictingShortNamesData();
        unset($data['shippingKind']);

        $this->assertHydrates($data, ConflictingShortNamesDTO::class, function (ConflictingShortNamesDTO $dto) {
            $this->assertSame(ShippingKind::Standard, $dto->shippingKind);
        });
    }

    public function testReportsInvalidEnumOfSameShortNamedType(): void
    {
        $data = $this->conflictingShortNamesData();
        $data['billingKind'] = 'unknown';

        $this->assertHydrationFails($data, ConflictingShortNamesDTO::class, 'invalid_value', 'billingKind');
    }

    public function testRoundtripsDtoWithSameShortNamedTypes(): void
    {
        $this->assertRoundtrip($this->conflictingShortNamesData(), ConflictingShortNamesDTO::class);
    }

    public function testHydratesArrayOfSameShortNamedTypes(): void
    {
        $this->assertArrayHydrates(
            [['street' => 'Main 1', 'vatId' => 'VAT-1']],
            BillingAddress::class,
            1
        );
        $this->assertArrayHydrates(
            [['street' => 'Side 2', 'recipient' => 'Jane']],
            ShippingAddress::class,
            1
        );
    }

    public function testHydratesDiscriminatedArrayOfSameShortNamedTypes(): void
    {
        $data = [
            ['type' => 'billing', 'street' => 'Main 1', 'vatId' => 'VAT-1'],
            ['type' => 'shipping', 'street' => 'Side 2', 'recipient' => 'Jane'],
        ];

        $this->assertDiscriminatedArrayHydrates($data, 'type', self::ADDRESS_MAPPING, function (array $results) {
            $this->assertCount(2, $results);
            $this->assertInstanceOf(BillingAddress::class, $results[0]);
            $this->assertInstanceOf(ShippingAddress::class, $results[1]);
        });
    }

    public function testDehydratesArrayOfSameShortNamedTypes(): void
    {
        $result = $this->dehydrator->dehydrateArray([
            new BillingAddress('Main 1', 'VAT-1'),
            new ShippingAddress('Side 2', 'Jane'),
        ]);

        $this->assertSame([
            ['street' => 'Main 1', 'vatId' => 'VAT-1'],
            ['street' => 'Side 2', 'recipient' => 'Jane'],
        ], $result);
    }

    // =========================================================================
    // Classes sharing a short name with Hydra runtime classes
    // =========================================================================

    public function testHydratesDtoNamedLikeHydraRuntimeClasses(): void
    {
        $this->assertHydrates(['reason' => 'boom'], HydrationError::class, function (HydrationError $dto) {
            $this->assertSame('boom', $dto->reason);
        });
        $this->assertHydrates(['reason' => 'bang'], DehydrationError::class, function (DehydrationError $dto) {
            $this->assertSame('bang', $dto->reason);
        });
    }

    public function testReportsErrorsForDtoNamedLikeHydraErrorClass(): void
    {
        $this->assertHydrationFails([], HydrationError::class, 'missing_key', 'reason');
        $this->assertHydrationFails(['reason' => 1], HydrationError::class, 'type_mismatch', 'reason');
    }

    public function testRoundtripsDtoNamedLikeHydraRuntimeClasses(): void
    {
        $this->assertRoundtrip(['reason' => 'boom'], HydrationError::class);
        $this->assertRoundtrip(['reason' => 'bang'], DehydrationError::class);
    }

    public function testRoundtripsNestedTypesNamedLikeHydraRuntimeClasses(): void
    {
        $data = [
            'hydrationError' => ['reason' => 'boom'],
            'dehydrationError' => ['reason' => 'bang'],
            'history' => [['reason' => 'first'], ['reason' => 'second']],
            'code' => 'invalid',
        ];

        $this->assertHydrates($data, HydratorRuntime::class, function (HydratorRuntime $dto) {
            $this->assertSame('boom', $dto->hydrationError->reason);
            $this->assertSame('bang', $dto->dehydrationError->reason);
            $this->assertInstanceOf(HydrationError::class, $dto->history[1]);
            $this->assertSame('second', $dto->history[1]->reason);
            $this->assertSame(HydrationErrorCode::Invalid, $dto->code);
        });
        $this->assertRoundtrip($data, HydratorRuntime::class);
    }

    public function testReportsNestedErrorsForTypesNamedLikeHydraRuntimeClasses(): void
    {
        try {
            $this->hydrator->hydrate([
                'hydrationError' => [],
                'dehydrationError' => ['reason' => 'bang'],
                'history' => [['reason' => 1]],
            ], HydratorRuntime::class);
            $this->fail('Expected HydrationFailure was not thrown');
        } catch (HydrationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(2, $errors);
            $this->assertSame('hydrationError.reason', $errors[0]->fullPath);
            $this->assertSame('missing_key', $errors[0]->code->value);
            $this->assertStringStartsWith('history[', $errors[1]->fullPath);
            $this->assertSame('type_mismatch', $errors[1]->code->value);
        }
    }

    public function testArrayHydrationOfTypesNamedLikeHydraRuntimeClasses(): void
    {
        $this->assertArrayHydrates([['reason' => 'boom']], HydrationError::class, 1);

        $this->assertDiscriminatedArrayHydrates(
            [['kind' => 'h', 'reason' => 'boom'], ['kind' => 'd', 'reason' => 'bang']],
            'kind',
            ['h' => HydrationError::class, 'd' => DehydrationError::class],
            function (array $results) {
                $this->assertInstanceOf(HydrationError::class, $results[0]);
                $this->assertInstanceOf(DehydrationError::class, $results[1]);
            }
        );
    }

    public function testDehydratesArrayOfTypesNamedLikeHydraRuntimeClasses(): void
    {
        $result = $this->dehydrator->dehydrateArray([
            new HydrationError('boom'),
            new DehydrationError('bang'),
        ]);

        $this->assertSame([['reason' => 'boom'], ['reason' => 'bang']], $result);
    }

    /**
     * @return array<string, mixed>
     */
    private function conflictingShortNamesData(): array
    {
        return [
            'billing' => ['street' => 'Main 1', 'vatId' => 'VAT-1'],
            'shipping' => ['street' => 'Side 2', 'recipient' => 'Jane'],
            'billingHistory' => [
                ['street' => 'Old 1', 'vatId' => 'VAT-0'],
                ['street' => 'Older 1', 'vatId' => 'VAT-00'],
            ],
            'shippingByName' => [
                'home' => ['street' => 'Home 3', 'recipient' => 'John'],
            ],
            'primary' => ['type' => 'shipping', 'street' => 'Side 2', 'recipient' => 'Jane'],
            'addresses' => [
                ['type' => 'billing', 'street' => 'Main 1', 'vatId' => 'VAT-1'],
                ['type' => 'shipping', 'street' => 'Side 2', 'recipient' => 'Jane'],
            ],
            'addressesByName' => [
                'office' => ['type' => 'billing', 'street' => 'Main 1', 'vatId' => 'VAT-1'],
            ],
            'billingKind' => 'receipt',
            'billingLabel' => 'B-7',
            'shippingLabel' => 'S-9',
            'shippingKind' => 2,
        ];
    }
}

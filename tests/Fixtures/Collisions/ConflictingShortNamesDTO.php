<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Collisions;

use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Attributes\MapOf;
use DrabekDigital\Hydra\Attributes\ObjectType;
use DrabekDigital\Hydra\Tests\Fixtures\Collisions\Billing\Address as BillingAddress;
use DrabekDigital\Hydra\Tests\Fixtures\Collisions\Billing\AsLabel as AsBillingLabel;
use DrabekDigital\Hydra\Tests\Fixtures\Collisions\Billing\Kind as BillingKind;
use DrabekDigital\Hydra\Tests\Fixtures\Collisions\Shipping\Address as ShippingAddress;
use DrabekDigital\Hydra\Tests\Fixtures\Collisions\Shipping\AsLabel as AsShippingLabel;
use DrabekDigital\Hydra\Tests\Fixtures\Collisions\Shipping\Kind as ShippingKind;

readonly class ConflictingShortNamesDTO
{
    /**
     * @param list<BillingAddress>            $billingHistory
     * @param array<string, ShippingAddress>  $shippingByName
     * @param list<AddressInterface>          $addresses
     * @param array<string, AddressInterface> $addressesByName
     */
    public function __construct(
        public BillingAddress $billing,
        public ShippingAddress $shipping,
        #[ListOf(BillingAddress::class)]
        public array $billingHistory,
        #[MapOf(value: ShippingAddress::class)]
        public array $shippingByName,
        #[ObjectType(
            discriminator: 'type',
            mapping: [
                'billing' => BillingAddress::class,
                'shipping' => ShippingAddress::class,
            ],
            passDiscriminator: false
        )]
        public AddressInterface $primary,
        #[ListOf(
            value: AddressInterface::class,
            discriminator: 'type',
            mapping: [
                'billing' => BillingAddress::class,
                'shipping' => ShippingAddress::class,
            ],
            passDiscriminator: false
        )]
        public array $addresses,
        #[MapOf(
            value: AddressInterface::class,
            discriminator: 'type',
            mapping: [
                'billing' => BillingAddress::class,
                'shipping' => ShippingAddress::class,
            ],
            passDiscriminator: false
        )]
        public array $addressesByName,
        public BillingKind $billingKind,
        #[AsBillingLabel]
        public string $billingLabel,
        #[AsShippingLabel]
        public string $shippingLabel,
        public ShippingKind $shippingKind = ShippingKind::Standard,
    ) {}
}

# Mappers


## Scalar + nullable
```php
<?php declare(strict_types=1);

use DrabekDigital\Hydra\Attributes\Undefinable;
use DrabekDigital\Hydra\Attributes\UndefinedDehydration;
use DrabekDigital\Hydra\Attributes\DefaultValue;

class Person
{
    public function __construct(
        public readonly string $name,

        public readonly int $age,

        public readonly bool $newsletterSubscriber,

        public readonly string $email,

        public readonly mixed $data,

        public readonly int|float $height,

        // If the field is not present in the input you can define default value via Default attribute
        // On dehydration the default value will be persisted
        // You cannot specify default assignment due to "Optional parameter declared before required parameter" error
        #[DefaultValue(null)]
        public readonly ?string $twitterProfile,

        // If the field is not present in the input and no default value is specified it will throw an error (also on dehydration)
        public readonly ?string $instagramProfile,

        // If the field is not present in the input it remains uninitialized.
        // On dehydration policy is explicit:
        // - SKIP: omit key
        // - AS_NULL: emit null
        // - AS_VALUE: emit value from undefinedAs
        #[Undefinable(onUndefinedDehydration: UndefinedDehydration::AS_NULL)]
        public readonly ?string $facebookProfile,
    ) {}
}
```

## Special types

```php
<?php declare(strict_types=1);

use DrabekDigital\Hydra\Attributes\DateTime;

enum Status: string {
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';
}

class Entry {
    public function __construct(
        #[DateTime(format: "Y-m-d H:i:s", timezone: "Europe/Prague")] // Default format is Y-m-d\TH:i:sP, if timezone is not specified UTC is used, DateTimeImmutable is used by default
        public readonly DateTimeInterface $lastSeen,

        public readonly DateTimeZone $timezone,

        public readonly Status $status,
    ) {}
}
```


## Arrays & lists

```php
<?php declare(strict_types=1);

use DrabekDigital\Hydra\Attributes\ArrayOf;
use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Attributes\MapOf;


enum VehicleType: string {
    case Car = 'car';
    case Truck = 'truck';
}

abstract class Vehicle {
    public function __construct(
        VehicleType $type,
    ) {}
}

class Car extends Vehicle {

    public function __construct(
        VehicleType $type,
        public readonly string $color,
    ) {
        parent::__construct($type);
    }
}

class Truck extends Vehicle {

    public function __construct(
        VehicleType $type,
        public readonly string $color,
    ) {
        parent::__construct($type);
    }
}

class Entry {
    public function __construct(
        #[ArrayOf('string')]
        public readonly array $tags = [],

        #[ListOf(Person::class)]
        public readonly array $friends,

        #[MapOf(key: 'string', value: Person::class)]
        public readonly array $enemies,

        #[ListOf(
            discriminator: 'type',
            passDiscriminator: false, // By default discriminator is passed to subtype constructor
            mapping: [
                VehicleType::Car->value => Car::class,
                VehicleType::Truck->value => Truck::class,
            ],
            value: Vehicle::class, // Optional, if specified then it has to be ancestor of all discriminator mapping values
        )]
        public readonly array $vehicles,
    ) {}
}
```

## Objects

```php
<?php declare(strict_types=1);

use DrabekDigital\Hydra\Attributes\ObjectType;

enum VehicleType: string {
    case Car = 'car';
    case Truck = 'truck';
}

abstract class Vehicle {
    public function __construct(
        VehicleType $type,
    ) {}
}

class Car extends Vehicle {

    public function __construct(
        VehicleType $type,
        public readonly string $color,
    ) {
        parent::__construct($type);
    }
}

class Truck extends Vehicle {

    public function __construct(
        VehicleType $type,
        public readonly string $color,
    ) {
        parent::__construct($type);
    }
}

class Entry {
    public function __construct(
        public readonly Person $person,

        #[ObjectType(
            discriminator: 'type',
            mapping: [
                VehicleType::Car->value => Car::class,
                VehicleType::Truck->value => Truck::class,
            ],
        )]
        public readonly Vehicle $vehicle,

        #[ObjectType(
            discriminator: 'type',
            passDiscriminator: false, // By default discriminator is passed to subtype constructor
            mapping: [
                VehicleType::Car->value => Car::class,
                VehicleType::Truck->value => Truck::class,
            ],
        )]
        public readonly Car|Truck $mainVehicle,
    ) {}
}
```

## Custom mappers (full weight approach)

```php
<?php declare(strict_types=1);

use DrabekDigital\Hydra\Attributes\CustomMapper;

class Entry {
    #[CustomMapper(mapper: MyCustomMapper::class)]
    public readonly MyCustomObject $customField,
}
```

Custom mapper is expected to be class implementing `DrabekDigital\Hydra\Mappers\Mapper` and provide
method for `hydrate` and `dehydrate` methods.

It can be registered when creating the hydrator or dehydrator... If it was not registered it will be automatically instantiated when needed.


Example of custom mapper:
```php
<?php declare(strict_types=1);

use DrabekDigital\Hydra\Mappers\Mapper;
use DrabekDigital\Hydra\Exceptions\MapperException;

class MyCustomMapper implements Mapper {
    public function hydrate(mixed $value, string $key, string $fullPath): mixed {
        if (!is_array($value)) {
            throw new MapperException("Expected array for MyCustomObject, got " . gettype($value));
        }
        return new MyCustomObject($value);
    }

    public function dehydrate(mixed $value, string $key, string $fullPath): mixed {
        if (!$value instanceof MyCustomObject) {
            throw new MapperException("Expected MyCustomObject, got " . get_class($value));
        }
        return $value->toArray();
    }
}
```

### Custom Mapper Attributes

You can create your own semantic attribute by implementing the `MapperAttribute` interface.
This allows you to create clean, reusable attributes instead of using `#[CustomMapper]` everywhere.

```php
<?php declare(strict_types=1);

use Attribute;
use DrabekDigital\Hydra\Attributes\MapperAttribute;
use DrabekDigital\Hydra\Mappers\Mapper;

// 1. Create your mapper
class MoneyMapper implements Mapper {
    public function hydrate(mixed $value, string $key, string $fullPath): mixed {
        // Convert cents (int) to Money object
        return new Money((int)$value);
    }

    public function dehydrate(mixed $value, string $key, string $fullPath): mixed {
        // Convert Money object to cents (int)
        return $value->cents;
    }
}

// 2. Create your attribute implementing MapperAttribute
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class AsMoneyInCents implements MapperAttribute {
    public function getMapperClass(): string {
        return MoneyMapper::class;
    }
}
```

Now use it on your DTOs:
```php
<?php declare(strict_types=1);

class Invoice {
    public function __construct(
        public readonly string $id,
        
        #[AsMoneyInCents]
        public readonly Money $amount,
        
        #[AsMoneyInCents]
        public readonly Money $tax,
    ) {}
}
```

The input/output will be:
```json
{
    "id": "INV-001",
    "amount": 9999,
    "tax": 2100
}
```

This approach is cleaner than `#[CustomMapper(mapper: MoneyMapper::class)]` and makes your DTOs more readable.

## Custom mappers (lightweight approach aka formatters)

```php
<?php declare(strict_types=1);

use DrabekDigital\Hydra\Attributes\CustomFormatter;

class Money {
    public function __construct(
        #[CustomFormatter(
            hydrate: static function (string $value): float {
                return (float) str_replace(',', '.', $value);
            },
            dehydrate: static function (float $value): string {
                return number_format($value, 2, ',', '.');
            },
        )]
        public readonly float $value,
        public readonly string $currency,
    ) {}
}
```

> **Note:** PHP attribute arguments must be constant expressions. Arrow functions (`fn() =>`) are **not** valid — use `static function() {}` instead. First-class callables (`ClassName::method(...)`) also work.
>
> **Cache note:** For closure-based formatters, Hydra uses a best-effort closure fingerprint for structure-hash invalidation (source slice + scope + static variables). It is heuristic and may not be fully reliable in all runtimes (for example dynamically generated/eval-ed closures). If you need deterministic invalidation, prefer class-based custom mappers.

Limitation of this approach is that you cannot check nor return any errors. Note that for custom formatters, throwing exceptions is not a supported pattern — use a custom mapper instead if you need controlled error propagation via `MapperException`.

## Extra fields

By default all extra fields are not allowed, to allow them you can use `#[AllowExtraKeys]` attribute, but they won't be mapped, just the hydration will pass ignoring them.

## Skipped fields

Whe you want to skip a field from being hydrated or dehydrated you can use
`#[Skip(hydration: true, dehydration: true)]` attribute. Beware that default values for both fields is `true`!
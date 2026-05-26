# Validators

## Validators

- Integer & float
  - Range (min, max, inclusive, exclusive) - `NumberRange`
- Strings
  - `LengthRange` (min, max) with one particular alias `NonEmptyString`
  - Regex - `StringMatches`
  - URL
- Array
  - Count (min, max)
  - Unique items (including custom comparator)
- Date & time
    - `DateTimeRange` (min, max, inclusive, exclusive)
- Custom

--------------------------------------------

```php
<?php declare(strict_types=1);

use DrabekDigital\Hydra\Attributes\Validation\NumberRange;
use DrabekDigital\Hydra\Attributes\Validation\StringMatches;
use DrabekDigital\Hydra\Attributes\Validation\URL;
use DrabekDigital\Hydra\Attributes\Validation\LengthRange;
use DrabekDigital\Hydra\Attributes\Validation\UniqueItems;
use DrabekDigital\Hydra\Attributes\Validation\Count;
use DrabekDigital\Hydra\Attributes\Validation\DateTimeRange;
use DrabekDigital\Hydra\Attributes\Validation\CustomValidator;
class Person
{
    public function __construct(
        #[LengthRange(min: 3, max: 100)]
        public readonly string $name,

        #[NumberRange(min: 0, max: 120, bounds: "[]")]
        public readonly int $age,

        #[StringMatches(regex: "^.*@.*\.*$")]
        public readonly string $email,

        #[URL]
        public readonly ?string $facebookProfile = null,

        #[UniqueItems(comparator: fn (string $a, string $b) => strtolower($a) === strtolower($b))] // By default === is used
        #[Count(min: 1, max: 10)]
        public readonly array $tags,

        #[DateTimeRange(min: new DateTimeImmutable("1900-01-01"), max: new DateTimeImmutable("2026-12-31"), bounds: "[]")]
        public readonly DateTimeInterface $birthday,

        #[CustomValidator(validator: MyCustomValidator::class)]
        public readonly MyCustomObject $customField,
    ) {}
}
```

```php
<?php declare(strict_types=1);

use DrabekDigital\Hydra\Validators\Validator;
use DrabekDigital\Hydra\Errors\ValidationError;

class MyCustomValidator implements Validator {
    public function validate(mixed $value, string $key, string $fullPath): ?ValidationError {
        if (!$value instanceof MyCustomObject || $value->name !== "foo") {
            return new ValidationError(
                code: CustomValidationErrorCodes::CODE_CUSTOM,
                programmerMessage: "Custom validator failed",
                key: $key,
                fullPath: $fullPath,
            );
        }
    }
}
```

The validator can be registered explicitly, otherwise it will be auto-instantiated when needed:

```php
<?php declare(strict_types=1);

use DrabekDigital\Hydra\Validator;

$validator = new Validator();
$validator->registerValidator(MyCustomValidator::class, new MyCustomValidator());
$validator->validate($object);
```
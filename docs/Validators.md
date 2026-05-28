# Validators

## Validators

- Integer & float
  - Range (min, max, inclusive, exclusive) - `NumberRange`
- Strings
  - `LengthRange` (min, max) with one particular alias `NonEmptyString`
  - Regex - `StringMatches`
  - URL
  - Email
  - UUID
  - IP
  - Hostname
- Array
  - Count (min, max)
  - Unique items (including custom comparator; required for objects/resources)
- Any
  - NotNull
  - IsNull
  - Choice (supports `choices` and `enumClass`)
- Date & time
    - `DateTimeRange` (min, max, inclusive, exclusive)
- Custom

### Type compatibility and `null`

Typed validation attributes implement `TypedValidationAttribute` and declare:

- **`supportedValueTypes`** — which property/value types the rule applies to
- **`onNull`** (`NullValidationBehavior`) — per-rule behavior when the value is `null`:
  - `SkipWhenNull` (default for most rules) — skip validation
  - `FailWhenNull` — treat `null` as invalid for this rule
  - `ValidateAlways` — used by `NotNull` / `IsNull`

At **DTO definition time**, Hydra checks that each validation attribute is compatible with the property's declared PHP type. Incompatible combinations (e.g. `#[Email]` on `int $age`) throw `InvalidArgumentException` on the first `validate()` call.

At **runtime**, if a value's type does not match the rule (e.g. hydration produced `int` for a `string` property with `#[Email]`), validation fails with `rule_not_applicable` instead of silently skipping the rule.

| Attribute | Value types | Default `onNull` |
|-----------|-------------|------------------|
| `NotNull`, `IsNull` | any | `ValidateAlways` |
| `LengthRange`, `NonEmptyString`, `StringMatches`, `URL`, `Email`, `Uuid`, `Ip`, `Hostname` | `string` | `SkipWhenNull` |
| `NumberRange` | `int`, `float` | `SkipWhenNull` |
| `Count`, `UniqueItems` | `array` | `SkipWhenNull` |
| `DateTimeRange` | `DateTimeInterface` | `SkipWhenNull` |
| `Choice` | scalars / `enumClass` | `SkipWhenNull` |

Example with explicit null handling:

```php
#[LengthRange(min: 1, onNull: NullValidationBehavior::FailWhenNull)]
public ?string $nickname,
```

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
use DrabekDigital\Hydra\Validators\Validator;
use DrabekDigital\Hydra\Errors\ValidationError;

class Person
{
    public function __construct(
        #[LengthRange(min: 3, max: 100)]
        public readonly string $name,

        #[NumberRange(min: 0, max: 120, bounds: "[]")]
        public readonly int $age,

        #[StringMatches(pattern: '/^[^@]+@[^@]+\.[^@]+$/')]
        public readonly string $email,

        #[URL]
        public readonly ?string $facebookProfile = null,

        /** @var list<string> */
        #[UniqueItems(comparator: function (mixed $a, mixed $b): bool {
            return is_string($a) && is_string($b) && strcasecmp($a, $b) === 0;
        })]
        #[Count(min: 1, max: 10)]
        public readonly array $tags,

        #[DateTimeRange(min: new DateTimeImmutable("1900-01-01"), max: new DateTimeImmutable("2026-12-31"), bounds: "[]")]
        public readonly DateTimeInterface $birthday,


        #[CustomValidator(validator: MyCustomValidator::class)]
        public readonly MyCustomObject $customField,

        #[RequiresFooName(expectedName: 'foo')]
        public readonly MyCustomObject $customField2,
    ) {}
}


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

### Custom validation attribute

Define an attribute that implements `ValidationConstraint` and points to your validator class. Hydra picks it up automatically — no `#[CustomValidator]` needed.

**1. Attribute** (config + link to validator):

```php
<?php declare(strict_types=1);

use DrabekDigital\Hydra\Attributes\Validation\ValidationConstraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class RequiresFooName implements ValidationConstraint
{
    public function __construct(
        public readonly string $expectedName = 'foo',
    ) {}

    public function validatorClass(): string
    {
        return RequiresFooNameValidator::class;
    }
}
```

**2. Validator** (implements `AttributeValidator`, receives the attribute instance):

```php
<?php declare(strict_types=1);

use DrabekDigital\Hydra\Attributes\Validation\ValidationConstraint;
use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Validators\AttributeValidator;

final class RequiresFooNameValidator implements AttributeValidator
{
    public function validateWithConstraint(
        mixed $value,
        string $key,
        string $fullPath,
        ValidationConstraint $constraint,
    ): ?ValidationError {
        if (!$constraint instanceof RequiresFooName) {
            return new ValidationError(/* ... */);
        }

        if (!$value instanceof MyCustomObject || $value->name !== $constraint->expectedName) {
            return new ValidationError(/* ... */);
        }

        return null;
    }
}
```

**3. Use on the DTO** — only your attribute:

```php
#[RequiresFooName(expectedName: 'foo')]
public readonly MyCustomObject $customField,
```

Register the validator when you need DI or a shared instance:

```php
$validator = new Validator();
$validator->registerValidator(RequiresFooNameValidator::class, new RequiresFooNameValidator());
$validator->validate($object);
```

#### Legacy: `#[CustomValidator]`

For validators without a dedicated attribute, `#[CustomValidator(MyValidator::class)]` still works. The validator implements `Validator` only and does not receive attribute config.
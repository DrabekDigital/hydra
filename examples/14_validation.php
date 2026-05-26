<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\Validation\Count;
use DrabekDigital\Hydra\Attributes\Validation\CustomValidator;
use DrabekDigital\Hydra\Attributes\Validation\LengthRange;
use DrabekDigital\Hydra\Attributes\Validation\NumberRange;
use DrabekDigital\Hydra\Attributes\Validation\StringMatches;
use DrabekDigital\Hydra\Attributes\Validation\UniqueItems;
use DrabekDigital\Hydra\Attributes\Validation\URL;
use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;
use DrabekDigital\Hydra\Exceptions\ValidationFailure;
use DrabekDigital\Hydra\Validators\Validator as ValidatorContract;

final readonly class PromoCode
{
    public function __construct(public string $value) {}
}

final class PromoCodeValidator implements ValidatorContract
{
    public function validate(mixed $value, string $key, string $fullPath): ?ValidationError
    {
        if (!$value instanceof PromoCode || !str_starts_with($value->value, 'HYDRA-')) {
            return new ValidationError(
                code: ValidationErrorCode::CUSTOM,
                programmerMessage: 'Promo code must start with HYDRA-',
                key: $key,
                fullPath: $fullPath,
            );
        }

        return null;
    }
}

final readonly class CampaignDTO
{
    public function __construct(
        #[NumberRange(min: 1, max: 100)]
        public int $discountPercent,
        #[StringMatches('/^[A-Z]{3,8}$/')]
        public string $code,
        #[URL(allowedSchemes: ['https'])]
        public string $landingPage,
        #[Count(min: 1, max: 3)]
        #[UniqueItems]
        public array $tags,
        #[CustomValidator(PromoCodeValidator::class)]
        public PromoCode $promoCode,
        #[LengthRange(min: 3)]
        public string $ownerName,
    ) {}
}

$validator->registerValidator(PromoCodeValidator::class, new PromoCodeValidator());

section('Valid object');
$valid = new CampaignDTO(
    discountPercent: 20,
    code: 'SPRING',
    landingPage: 'https://example.com/campaign',
    tags: ['new', 'sale'],
    promoCode: new PromoCode('HYDRA-2026'),
    ownerName: 'Alice',
);
try {
    $validator->validate($valid);
    echo "Validation passed.\n";
} catch (ValidationFailure $e) {
    errors($e->getErrors());
}

section('Invalid object (all errors collected)');
$invalid = new CampaignDTO(
    discountPercent: 0,
    code: 'bad',
    landingPage: 'ftp://example.com',
    tags: ['dup', 'dup', 'dup', 'dup'],
    promoCode: new PromoCode('WRONG'),
    ownerName: 'Al',
);
try {
    $validator->validate($invalid);
} catch (ValidationFailure $e) {
    errors($e->getErrors());
}

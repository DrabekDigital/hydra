<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\Validation\CustomValidator;
use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;
use DrabekDigital\Hydra\Exceptions\ValidationFailure;
use DrabekDigital\Hydra\Validators\Validator as ValidatorInterface;

class LuhnValidator implements ValidatorInterface
{
    public function validate(mixed $value, string $key, string $fullPath): ?ValidationError
    {
        $digits = preg_replace('/\D/', '', (string) $value);
        if ($digits === '' || !$this->luhnCheck($digits)) {
            return new ValidationError(
                code: ValidationErrorCode::CUSTOM,
                programmerMessage: "'{$key}' is not a valid card number (Luhn check failed)",
                key: $key,
                fullPath: $fullPath,
            );
        }

        return null;
    }

    private function luhnCheck(string $digits): bool
    {
        $sum = 0;
        $flip = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $d = (int) $digits[$i];
            if ($flip) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
            $flip = !$flip;
        }

        return $sum % 10 === 0;
    }
}

final class PaymentDTO
{
    public function __construct(
        public readonly string $amount,
        #[CustomValidator(LuhnValidator::class)]
        public readonly string $cardNumber,
    ) {}
}

$validator->registerValidator(LuhnValidator::class, new LuhnValidator());

$validData = ['amount' => '99.99', 'cardNumber' => '4532015112830366'];

section('Input');
input_dump($validData);
section('Valid — card number passes Luhn check');
$dto = $hydrator->hydrate($validData, PaymentDTO::class);
try {
    $validator->validate($dto);
    echo "cardNumber: {$dto->cardNumber} ✓\n";
} catch (ValidationFailure $e) {
    errors($e->getErrors());
}

$invalidData = ['amount' => '9.99', 'cardNumber' => '1234567890123456'];

section('Input');
input_dump($invalidData);
section('Invalid — card number fails Luhn check');
$bad = $hydrator->hydrate($invalidData, PaymentDTO::class);
try {
    $validator->validate($bad);
} catch (ValidationFailure $e) {
    errors($e->getErrors());
}

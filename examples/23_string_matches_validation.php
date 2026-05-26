<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\Validation\StringMatches;
use DrabekDigital\Hydra\Exceptions\ValidationFailure;

final class ContactDTO
{
    public function __construct(
        public readonly string $name,
        #[StringMatches(pattern: '/^\+?[0-9\s\-]{7,15}$/')]
        public readonly string $phone,
        #[StringMatches(pattern: '/^[A-Z]{2,3}$/', message: 'Must be a 2–3 letter ISO country code')]
        public readonly string $countryCode,
        #[StringMatches(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public readonly string $birthDate,
    ) {}
}

$validData = [
    'name'        => 'Alice',
    'phone'       => '+420 123 456 789',
    'countryCode' => 'CZ',
    'birthDate'   => '1990-05-15',
];

section('Input');
input_dump($validData);
section('Valid — all patterns match');
$dto = $hydrator->hydrate($validData, ContactDTO::class);
$validator->validate($dto);
echo "phone: {$dto->phone}\n";
echo "countryCode: {$dto->countryCode}\n";
echo "birthDate: {$dto->birthDate}\n";

$invalidData = [
    'name'        => 'Bob',
    'phone'       => 'abc-xyz',
    'countryCode' => 'czech',
    'birthDate'   => '15/05/1990',
];

section('Input');
input_dump($invalidData);
section('Invalid — pattern mismatches');
$invalid = $hydrator->hydrate($invalidData, ContactDTO::class);
try {
    $validator->validate($invalid);
} catch (ValidationFailure $e) {
    errors($e->getErrors());
}

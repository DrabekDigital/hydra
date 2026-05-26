<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\NamingStrategy;
use DrabekDigital\Hydra\Attributes\Rename;
use DrabekDigital\Hydra\NamingStrategies\UnderscoreCase;

#[NamingStrategy(new UnderscoreCase())]
final class UserProfileDTO
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        #[Rename(from: 'legacy_email', to: 'public_email')]
        public readonly string $emailAddress,
    ) {}
}

$data = [
    'first_name' => 'Jan',
    'last_name' => 'Drabek',
    'legacy_email' => 'jan@example.com',
];

section('Input');
input_dump($data);
section('Hydrate (underscore_case input)');
$dto = $hydrator->hydrate($data, UserProfileDTO::class);
echo "firstName: {$dto->firstName}\n";
echo "lastName: {$dto->lastName}\n";
echo "emailAddress: {$dto->emailAddress}\n";

section('Dehydrate (underscore_case output)');
dump($dehydrator->dehydrate($dto));

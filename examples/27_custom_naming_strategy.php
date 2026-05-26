<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\NamingStrategy;
use DrabekDigital\Hydra\NamingStrategies\NamingStrategyInterface;

// Custom strategy: PascalCase keys ↔ camelCase PHP properties.
// e.g. property 'firstName' reads from / writes to key 'FirstName'.
class PascalCaseStrategy implements NamingStrategyInterface
{
    public function toInputKey(string $propertyName): string
    {
        return ucfirst($propertyName);
    }

    public function toOutputKey(string $propertyName): string
    {
        return ucfirst($propertyName);
    }
}

#[NamingStrategy(new PascalCaseStrategy())]
final class XmlImportDTO
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $emailAddress,
        public readonly int $yearOfBirth,
    ) {}
}

$data = [
    'FirstName'    => 'Jan',
    'LastName'     => 'Drabek',
    'EmailAddress' => 'jan@example.com',
    'YearOfBirth'  => 1990,
];

section('Input');
input_dump($data);
section('Hydrate (PascalCase input → camelCase properties)');
$dto = $hydrator->hydrate($data, XmlImportDTO::class);
echo "firstName: {$dto->firstName}\n";
echo "lastName: {$dto->lastName}\n";
echo "emailAddress: {$dto->emailAddress}\n";
echo "yearOfBirth: {$dto->yearOfBirth}\n";

section('Dehydrate (camelCase properties → PascalCase output)');
dump($dehydrator->dehydrate($dto));

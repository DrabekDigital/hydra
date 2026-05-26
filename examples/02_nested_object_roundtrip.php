<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

final class AddressDTO
{
    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly string $zip,
    ) {}
}

final class PersonDTO
{
    public function __construct(
        public readonly string $name,
        public readonly AddressDTO $address,
    ) {}
}

$data = [
    'name' => 'Alice',
    'address' => ['street' => '1 Main St', 'city' => 'Prague', 'zip' => '11000'],
];

section('Input');
input_dump($data);
section('Hydrate');
$person = $hydrator->hydrate($data, PersonDTO::class);
echo "name: {$person->name}\n";
echo "address: {$person->address->street}, {$person->address->city}\n";

section('Dehydrate');
dump($dehydrator->dehydrate($person));

<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

final class ScalarDTO
{
    public function __construct(
        public readonly string $name,
        public readonly int $age,
        public readonly float $height,
        public readonly bool $active,
    ) {}
}

$data = ['name' => 'Alice', 'age' => 30, 'height' => 1.72, 'active' => true];

section('Input');
input_dump($data);
section('Hydrate');
$dto = $hydrator->hydrate($data, ScalarDTO::class);
echo "name: {$dto->name}\n";
echo "age: {$dto->age}\n";
echo "height: {$dto->height}\n";
echo 'active: ' . ($dto->active ? 'true' : 'false') . "\n";

section('Dehydrate');
dump($dehydrator->dehydrate($dto));

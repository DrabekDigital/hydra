<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Exceptions\HydrationFailure;

final class StrictDTO
{
    public function __construct(
        public readonly string $name,
        public readonly int $age,
    ) {}
}

$data = ['name' => 'Alice'];
section('Input');
input_dump($data);
section('Missing required key');
try {
    $hydrator->hydrate($data, StrictDTO::class);
} catch (HydrationFailure $e) {
    errors($e->getErrors());
}

$data2 = ['name' => 42, 'age' => 'not-a-number'];
section('Input');
input_dump($data2);
section('Type mismatch');
try {
    $hydrator->hydrate($data2, StrictDTO::class);
} catch (HydrationFailure $e) {
    errors($e->getErrors());
}

$data3 = ['name' => 'Alice', 'age' => 30, 'unknown' => 'x'];
section('Input');
input_dump($data3);
section('Unexpected key');
try {
    $hydrator->hydrate($data3, StrictDTO::class);
} catch (HydrationFailure $e) {
    errors($e->getErrors());
}

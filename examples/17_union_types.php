<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Exceptions\HydrationFailure;

final class MeasurementDTO
{
    public function __construct(
        public readonly int|float $value,
        public readonly string|int $identifier,
    ) {}
}

$data1 = ['value' => 42, 'identifier' => 'sensor-1'];
section('Input');
input_dump($data1);
section('int|float — receives int');
$dto = $hydrator->hydrate($data1, MeasurementDTO::class);
echo 'value: ' . $dto->value . ' (' . gettype($dto->value) . ")\n";
echo "identifier: {$dto->identifier}\n";

$data2 = ['value' => 3.14, 'identifier' => 99];
section('Input');
input_dump($data2);
section('int|float — receives float');
$dto2 = $hydrator->hydrate($data2, MeasurementDTO::class);
echo 'value: ' . $dto2->value . ' (' . gettype($dto2->value) . ")\n";
echo 'identifier: ' . $dto2->identifier . ' (' . gettype($dto2->identifier) . ")\n";

section('Dehydrate');
dump($dehydrator->dehydrate($dto2));

$data3 = ['value' => 'not-a-number', 'identifier' => 1];
section('Input');
input_dump($data3);
section('Type not in union → error');
try {
    $hydrator->hydrate($data3, MeasurementDTO::class);
} catch (HydrationFailure $e) {
    errors($e->getErrors());
}

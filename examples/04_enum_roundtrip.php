<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Exceptions\HydrationFailure;

enum StatusEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

enum PriorityEnum: int
{
    case Low = 1;
    case High = 3;
}

final class EnumDTO
{
    public function __construct(
        public readonly StatusEnum $status,
        public readonly PriorityEnum $priority,
    ) {}
}

$data = ['status' => 'active', 'priority' => 3];
section('Input');
input_dump($data);
section('Hydrate');
$dto = $hydrator->hydrate($data, EnumDTO::class);
echo "status: {$dto->status->value}\n";
echo "priority: {$dto->priority->name} ({$dto->priority->value})\n";

section('Dehydrate');
dump($dehydrator->dehydrate($dto));

$bad = ['status' => 'unknown', 'priority' => 99];
section('Input');
input_dump($bad);
section('Invalid enum value');
try {
    $hydrator->hydrate($bad, EnumDTO::class);
} catch (HydrationFailure $e) {
    errors($e->getErrors());
}

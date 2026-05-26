<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\DateTime;
use DrabekDigital\Hydra\Exceptions\HydrationFailure;

final class EventDTO
{
    public function __construct(
        public readonly string $name,
        #[DateTime(format: 'Y-m-d\TH:i:sP', timezone: 'UTC')]
        public readonly \DateTimeImmutable $startsAt,
        #[DateTime(format: 'Y-m-d H:i:s')]
        public readonly ?\DateTimeImmutable $endsAt = null,
    ) {}
}

$data = [
    'name' => 'Conference',
    'startsAt' => '2025-06-01T10:00:00+00:00',
    'endsAt' => '2025-06-01 18:00:00',
];
section('Input');
input_dump($data);
section('Hydrate');
$dto = $hydrator->hydrate($data, EventDTO::class);
echo "name: {$dto->name}\n";
echo "startsAt: " . $dto->startsAt->format('Y-m-d H:i:s T') . "\n";
echo "endsAt: " . $dto->endsAt->format('Y-m-d H:i:s') . "\n";

section('Dehydrate');
dump($dehydrator->dehydrate($dto));

$bad = ['name' => 'Bad', 'startsAt' => 'not-a-date'];
section('Input');
input_dump($bad);
section('Invalid format');
try {
    $hydrator->hydrate($bad, EventDTO::class);
} catch (HydrationFailure $e) {
    errors($e->getErrors());
}

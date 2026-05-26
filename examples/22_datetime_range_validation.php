<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\DateTime;
use DrabekDigital\Hydra\Attributes\Validation\DateTimeRange;
use DrabekDigital\Hydra\Exceptions\ValidationFailure;

final readonly class EventDTO
{
    public function __construct(
        public string $name,
        #[DateTime(format: 'Y-m-d\TH:i:sP', timezone: 'UTC')]
        #[DateTimeRange(
            min: new DateTimeImmutable('2025-01-01T00:00:00+00:00'),
            max: new DateTimeImmutable('2025-12-31T23:59:59+00:00'),
            bounds: '[]',
        )]
        public DateTimeImmutable $startsAt,
    ) {}
}

$validData = ['name' => 'Conference', 'startsAt' => '2025-06-15T10:00:00+00:00'];
section('Input');
input_dump($validData);
section('Valid date within range');
$dto = $hydrator->hydrate($validData, EventDTO::class);
try {
    $validator->validate($dto);
    echo "Validation passed.\n";
    echo "startsAt: " . $dto->startsAt->format('Y-m-d') . "\n";
} catch (ValidationFailure $e) {
    errors($e->getErrors());
}

$earlyData = ['name' => 'Past event', 'startsAt' => '2024-12-31T00:00:00+00:00'];
section('Input');
input_dump($earlyData);
section('Date before range minimum');
$tooEarly = $hydrator->hydrate($earlyData, EventDTO::class);
try {
    $validator->validate($tooEarly);
} catch (ValidationFailure $e) {
    errors($e->getErrors());
}

$lateData = ['name' => 'Future event', 'startsAt' => '2026-01-01T00:00:00+00:00'];
section('Input');
input_dump($lateData);
section('Date after range maximum');
$tooLate = $hydrator->hydrate($lateData, EventDTO::class);
try {
    $validator->validate($tooLate);
} catch (ValidationFailure $e) {
    errors($e->getErrors());
}

<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\MapOf;

final class LocationDTO
{
    public function __construct(
        public readonly string $city,
        public readonly string $country,
    ) {}
}

final class TravelLogDTO
{
    public function __construct(
        public readonly string $traveller,
        #[MapOf(key: 'string', value: 'int')]
        public readonly array $scores,
        #[MapOf(key: 'string', value: LocationDTO::class)]
        public readonly array $visited,
    ) {}
}

$data = [
    'traveller' => 'Jan',
    'scores' => ['paris' => 9, 'tokyo' => 10, 'berlin' => 7],
    'visited' => [
        'paris' => ['city' => 'Paris', 'country' => 'France'],
        'tokyo' => ['city' => 'Tokyo', 'country' => 'Japan'],
    ],
];

section('Input');
input_dump($data);
section('Hydrate');
$dto = $hydrator->hydrate($data, TravelLogDTO::class);
echo "traveller: {$dto->traveller}\n";
echo 'scores: ' . implode(', ', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($dto->scores), $dto->scores)) . "\n";
foreach ($dto->visited as $key => $loc) {
    echo "visited[{$key}]: {$loc->city}, {$loc->country}\n";
}

section('Dehydrate');
dump($dehydrator->dehydrate($dto));

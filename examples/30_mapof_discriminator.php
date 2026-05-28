<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\MapOf;

abstract readonly class VehicleByKeyDTO
{
    public function __construct(
        public string $brand,
        public int $year,
    ) {}
}

final readonly class CarByKeyDTO extends VehicleByKeyDTO
{
    public function __construct(string $brand, int $year, public int $doors) {
        parent::__construct($brand, $year);
    }
}

final readonly class MotorcycleByKeyDTO extends VehicleByKeyDTO
{
    public function __construct(string $brand, int $year, public int $engineCC) {
        parent::__construct($brand, $year);
    }
}

final readonly class GarageByKeyDTO
{
    /**
     * @param array<string, VehicleByKeyDTO> $vehicles
     */
    public function __construct(
        #[MapOf(
            key: 'string',
            value: VehicleByKeyDTO::class,
            discriminator: 'type',
            mapping: ['car' => CarByKeyDTO::class, 'motorcycle' => MotorcycleByKeyDTO::class],
            passDiscriminator: false,
        )]
        public array $vehicles,
    ) {}
}

$data = [
    'vehicles' => [
        'first' => ['type' => 'car', 'brand' => 'Toyota', 'year' => 2020, 'doors' => 4],
        'second' => ['type' => 'motorcycle', 'brand' => 'Honda', 'year' => 2019, 'engineCC' => 750],
    ],
];

section('Input');
input_dump($data);
section('Hydrate');
$garage = $hydrator->hydrate($data, GarageByKeyDTO::class);
foreach ($garage->vehicles as $key => $vehicle) {
    echo "vehicles[{$key}]: " . $vehicle::class . " — {$vehicle->brand} {$vehicle->year}\n";
}

section('Dehydrate');
dump($dehydrator->dehydrate($garage));

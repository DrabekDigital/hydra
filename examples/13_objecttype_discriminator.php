<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Attributes\ObjectType;

abstract readonly class VehicleDTO
{
    public function __construct(
        public string $brand,
        public int $year,
    ) {}
}

final readonly class CarDTO extends VehicleDTO
{
    public function __construct(string $brand, int $year, public int $doors) {
        parent::__construct($brand, $year);
    }
}

final readonly class MotorcycleDTO extends VehicleDTO
{
    public function __construct(string $brand, int $year, public int $engineCC) {
        parent::__construct($brand, $year);
    }
}

final readonly class GarageDTO
{
    /** @param list<VehicleDTO> $vehicles */
    public function __construct(
        #[ObjectType(
            discriminator: 'type',
            mapping: ['car' => CarDTO::class, 'motorcycle' => MotorcycleDTO::class],
            passDiscriminator: false,
        )]
        public VehicleDTO $featured,
        #[ListOf(
            value: VehicleDTO::class,
            discriminator: 'type',
            mapping: ['car' => CarDTO::class, 'motorcycle' => MotorcycleDTO::class],
            passDiscriminator: false,
        )]
        public array $vehicles,
    ) {}
}

$data = [
    'featured' => ['type' => 'car', 'brand' => 'Skoda', 'year' => 2024, 'doors' => 5],
    'vehicles' => [
        ['type' => 'car', 'brand' => 'Toyota', 'year' => 2020, 'doors' => 4],
        ['type' => 'motorcycle', 'brand' => 'Honda', 'year' => 2019, 'engineCC' => 750],
    ],
];

section('Input');
input_dump($data);
section('Hydrate');
$garage = $hydrator->hydrate($data, GarageDTO::class);
echo 'featured: ' . $garage->featured::class . " — {$garage->featured->brand}\n";
foreach ($garage->vehicles as $i => $v) {
    echo "vehicles[{$i}]: " . $v::class . " — {$v->brand} {$v->year}\n";
}

section('Dehydrate');
dump($dehydrator->dehydrate($garage));

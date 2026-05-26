<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Exceptions\HydrationFailure;

final class ProductDTO
{
    public function __construct(
        public readonly string $sku,
        public readonly string $name,
        public readonly int $price,
    ) {}
}

abstract class VehicleDTO
{
    public function __construct(
        public readonly string $brand,
        public readonly int $year,
    ) {}
}

final class CarDTO extends VehicleDTO
{
    public function __construct(string $brand, int $year, public readonly int $doors) {
        parent::__construct($brand, $year);
    }
}

final class TruckDTO extends VehicleDTO
{
    public function __construct(string $brand, int $year, public readonly int $loadKg) {
        parent::__construct($brand, $year);
    }
}

$productData = [
    ['sku' => 'A1', 'name' => 'Mouse', 'price' => 999],
    ['sku' => 'A2', 'name' => 'Keyboard', 'price' => 2499],
];
section('Input');
input_dump($productData);
section('hydrateArray — uniform type');
$products = $hydrator->hydrateArray($productData, className: ProductDTO::class);
foreach ($products as $p) {
    echo "  {$p->sku}: {$p->name} ({$p->price} cents)\n";
}

$vehicleData = [
    ['type' => 'car', 'brand' => 'Toyota', 'year' => 2023, 'doors' => 4],
    ['type' => 'truck', 'brand' => 'Ford', 'year' => 2022, 'loadKg' => 3000],
];
section('Input');
input_dump($vehicleData);
section('hydrateArray — discriminated');
$vehicles = $hydrator->hydrateArray($vehicleData, discriminator: 'type', mapping: ['car' => CarDTO::class, 'truck' => TruckDTO::class]);
foreach ($vehicles as $v) {
    echo '  ' . $v::class . ": {$v->brand} {$v->year}\n";
}

section('dehydrateArray');
dump($dehydrator->dehydrateArray($products));

$unknownData = [['type' => 'spaceship', 'brand' => 'SpaceX', 'year' => 2025]];
section('Input');
input_dump($unknownData);
section('Unknown discriminator value');
try {
    $hydrator->hydrateArray(
        $unknownData,
        discriminator: 'type',
        mapping: ['car' => CarDTO::class]
    );
} catch (HydrationFailure $e) {
    errors($e->getErrors());
}

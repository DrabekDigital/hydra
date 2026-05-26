<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\ObjectType;
use DrabekDigital\Hydra\Exceptions\HydrationFailure;

// passDiscriminator controls whether the discriminator key ('type') is forwarded
// to the nested DTO during hydration.
//
//   passDiscriminator: false (default) — 'type' is stripped before hydrating the nested DTO
//   passDiscriminator: true            — 'type' is passed through; the nested DTO must declare it

abstract readonly class ShapeDTO
{
    public function __construct(public string $color) {}
}

// Accepts 'type' as a field (passDiscriminator: true)
final readonly class CircleWithTypeDTO extends ShapeDTO
{
    public function __construct(string $color, public string $type, public float $radius) {
        parent::__construct($color);
    }
}

// Does NOT have 'type' field (passDiscriminator: false)
final readonly class CircleDTO extends ShapeDTO
{
    public function __construct(string $color, public float $radius) {
        parent::__construct($color);
    }
}

final readonly class CanvasStrippedDTO
{
    public function __construct(
        #[ObjectType(
            discriminator: 'type',
            mapping: ['circle' => CircleDTO::class],
            passDiscriminator: false,
        )]
        public ShapeDTO $shape,
    ) {}
}

final readonly class CanvasPassThroughDTO
{
    public function __construct(
        #[ObjectType(
            discriminator: 'type',
            mapping: ['circle' => CircleWithTypeDTO::class],
            passDiscriminator: true,
        )]
        public ShapeDTO $shape,
    ) {}
}

$input = ['shape' => ['type' => 'circle', 'color' => 'red', 'radius' => 5.0]];

section('Input');
input_dump($input);
section('passDiscriminator: false — type stripped, CircleDTO hydrated cleanly');
$canvas = $hydrator->hydrate($input, CanvasStrippedDTO::class);
echo 'class: ' . $canvas->shape::class . "\n";
echo "color: {$canvas->shape->color}, radius: {$canvas->shape->radius}\n";

section('passDiscriminator: true — type forwarded into CircleWithTypeDTO');
$canvas2 = $hydrator->hydrate($input, CanvasPassThroughDTO::class);
echo 'class: ' . $canvas2->shape::class . "\n";
echo "type field: {$canvas2->shape->type}\n";

section('passDiscriminator: false with strict DTO that has no type field — works fine');
dump($dehydrator->dehydrate($canvas));

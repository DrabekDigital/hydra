<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\DefaultValue;
use DrabekDigital\Hydra\Attributes\Undefinable;
use DrabekDigital\Hydra\Exceptions\HydrationFailure;

final class NullableUndefinableDTO
{
    public function __construct(
        public readonly string $required,
        public readonly ?string $nullable,
        #[DefaultValue('fallback')]
        public readonly string $withDefault,
        #[Undefinable]
        public readonly ?string $optional,
    ) {}
}

$data = ['required' => 'yes', 'nullable' => 'present', 'optional' => 'set'];
section('Input');
input_dump($data);
section('Nullable with value');
$dto = $hydrator->hydrate($data, NullableUndefinableDTO::class);
echo "nullable: {$dto->nullable}\n";
echo "withDefault: {$dto->withDefault}\n";
echo "optional: {$dto->optional}\n";

$data2 = ['required' => 'yes', 'nullable' => null];
section('Input');
input_dump($data2);
section('Nullable as null + default applied + undefinable missing');
$dto2 = $hydrator->hydrate($data2, NullableUndefinableDTO::class);
echo "nullable: null\n";
echo "withDefault: {$dto2->withDefault}\n";
echo 'optional initialized: ' . (isset($dto2->optional) ? 'yes' : 'no') . "\n";

$data3 = ['nullable' => null];
section('Input');
input_dump($data3);
section('Missing required key');
try {
    $hydrator->hydrate($data3, NullableUndefinableDTO::class);
} catch (HydrationFailure $e) {
    errors($e->getErrors());
}

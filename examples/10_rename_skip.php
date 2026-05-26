<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\AllowExtraKeys;
use DrabekDigital\Hydra\Attributes\Rename;
use DrabekDigital\Hydra\Attributes\Skip;
use DrabekDigital\Hydra\Exceptions\HydrationFailure;

final class StrictUserDTO
{
    public function __construct(
        public readonly string $name,
    ) {}
}

#[AllowExtraKeys]
final class ProfileDTO
{
    public function __construct(
        #[Rename(from: 'first_name', to: 'first_name')]
        public readonly string $firstName,
        #[Skip]
        public readonly ?string $internalNote = null,
        public readonly string $role = 'member',
    ) {}
}

$data = ['name' => 'Alice', 'extra' => 'x'];
section('Input');
input_dump($data);
section('Unexpected key rejected by default');
try {
    $hydrator->hydrate($data, StrictUserDTO::class);
} catch (HydrationFailure $e) {
    errors($e->getErrors());
}

$data2 = [
    'first_name' => 'Alice',
    'role' => 'admin',
    'internalNote' => 'ignored by #[Skip]',
    'anything' => 'allowed',
];
section('Input');
input_dump($data2);
section('AllowExtraKeys ignores unknown fields');
$profile = $hydrator->hydrate($data2, ProfileDTO::class);
echo "firstName: {$profile->firstName}\n";
echo 'internalNote: ' . ($profile->internalNote === null ? 'null (skipped)' : $profile->internalNote) . "\n";
echo "role: {$profile->role}\n";

section('Dehydrate (Skip omits field, Rename applies)');
dump($dehydrator->dehydrate($profile));

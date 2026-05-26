<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Attributes\Validation\Count;
use DrabekDigital\Hydra\Attributes\Validation\UniqueItems;
use DrabekDigital\Hydra\Exceptions\ValidationFailure;

final class TeamDTO
{
    public function __construct(
        public readonly string $name,
        #[ListOf('string')]
        #[Count(min: 2, max: 5)]
        #[UniqueItems]
        public readonly array $members,
        #[Count(min: 1, max: 3)]
        public readonly array $tags,
    ) {}
}

$validData = [
    'name'    => 'Alpha',
    'members' => ['Alice', 'Bob', 'Carol'],
    'tags'    => ['php', 'backend'],
];

section('Input');
input_dump($validData);
section('Valid — count and uniqueness satisfied');
$team = $hydrator->hydrate($validData, TeamDTO::class);
$validator->validate($team);
echo "team: {$team->name}, members: " . count($team->members) . "\n";

$tooManyData = [
    'name'    => 'Beta',
    'members' => ['A', 'B', 'C', 'D', 'E', 'F'],
    'tags'    => ['php'],
];

section('Input');
input_dump($tooManyData);
section('Invalid — too many members (max 5)');
$tooMany = $hydrator->hydrate($tooManyData, TeamDTO::class);
try {
    $validator->validate($tooMany);
} catch (ValidationFailure $e) {
    errors($e->getErrors());
}

$dupesData = [
    'name'    => 'Gamma',
    'members' => ['Alice', 'Bob', 'Alice'],
    'tags'    => ['php'],
];

section('Input');
input_dump($dupesData);
section('Invalid — duplicate members');
$dupes = $hydrator->hydrate($dupesData, TeamDTO::class);
try {
    $validator->validate($dupes);
} catch (ValidationFailure $e) {
    errors($e->getErrors());
}

$tooFewData = [
    'name'    => 'Delta',
    'members' => ['Solo'],
    'tags'    => [],
];

section('Input');
input_dump($tooFewData);
section('Invalid — too few members (min 2) + empty tags (min 1)');
$tooFew = $hydrator->hydrate($tooFewData, TeamDTO::class);
try {
    $validator->validate($tooFew);
} catch (ValidationFailure $e) {
    errors($e->getErrors());
}

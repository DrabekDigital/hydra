<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Attributes\Validation\LengthRange;
use DrabekDigital\Hydra\Attributes\Validation\NumberRange;
use DrabekDigital\Hydra\Exceptions\ValidationFailure;

// Validation errors on nested objects and list items carry dotted full paths:
//   owner.name, members.0.name, members.1.name, …

final readonly class MemberDTO
{
    public function __construct(
        #[LengthRange(min: 3)]
        public string $name,
        #[NumberRange(min: 0, max: 100)]
        public int $score,
    ) {}
}

final readonly class TeamDTO
{
    public function __construct(
        #[LengthRange(min: 2)]
        public string $teamName,
        public MemberDTO $owner,
        #[ListOf(MemberDTO::class)]
        public array $members,
    ) {}
}

section('Valid team');
$valid = new TeamDTO(
    teamName: 'Hydra devs',
    owner: new MemberDTO('Alice', 95),
    members: [new MemberDTO('Bob', 80), new MemberDTO('Carol', 90)],
);
try {
    $validator->validate($valid);
    echo "Validation passed.\n";
} catch (ValidationFailure $e) {
    errors($e->getErrors());
}

section('Invalid — errors carry dotted paths');
$invalid = new TeamDTO(
    teamName: 'X',
    owner: new MemberDTO('Al', 150),
    members: [
        new MemberDTO('Bo', 80),
        new MemberDTO('Carol', -5),
    ],
);
try {
    $validator->validate($invalid);
} catch (ValidationFailure $e) {
    errors($e->getErrors());
}

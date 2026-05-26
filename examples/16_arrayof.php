<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\ArrayOf;
use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Attributes\Validation\LengthRange;
use DrabekDigital\Hydra\Exceptions\ValidationFailure;

// ArrayOf is a documentation/annotation attribute. It does NOT validate item types
// during hydration — the array passes through as-is. Its purpose is:
//   1. IDE type hints for the array contents
//   2. Validator uses it to recurse into nested objects for validation
//
// Contrast with ListOf: ListOf hydrates raw arrays into typed objects.
// ArrayOf leaves items untouched.

final readonly class CommentDTO
{
    public function __construct(
        public string $author,
        #[LengthRange(min: 1)]
        public string $text,
    ) {}
}

final class PostDTO
{
    public function __construct(
        // ListOf: each item is hydrated from array → CommentDTO
        #[ListOf(CommentDTO::class)]
        public readonly array $comments,
        // ArrayOf: items pass through as-is (no hydration), documents type for IDE/tools
        #[ArrayOf('string')]
        public readonly array $tags,
    ) {}
}

$data = [
    'comments' => [
        ['author' => 'Alice', 'text' => 'Great post!'],
        ['author' => 'Bob', 'text' => 'Thanks!'],
    ],
    'tags' => ['php', 'hydra', 'serialization'],
];

section('Hydrate — ListOf hydrates objects, ArrayOf passes through');
section('Input');
input_dump($data);
$post = $hydrator->hydrate($data, PostDTO::class);
echo 'comments[0] is CommentDTO: ' . ($post->comments[0] instanceof CommentDTO ? 'yes' : 'no') . "\n";
echo 'tags[0] is plain string: ' . (is_string($post->tags[0]) ? 'yes' : 'no') . "\n";

section('Dehydrate');
dump($dehydrator->dehydrate($post));

$invalidData = [
    'comments' => [
        ['author' => 'Alice', 'text' => ''],  // text too short
    ],
    'tags' => ['php'],
];
section('Input');
input_dump($invalidData);
section('Validator recurses into ArrayOf object items for validation');
$invalidPost = $hydrator->hydrate($invalidData, PostDTO::class);
try {
    $validator->validate($invalidPost);
} catch (ValidationFailure $e) {
    errors($e->getErrors());
}

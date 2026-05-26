<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\ListOf;

final class TagDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $color,
    ) {}
}

final class ArticleDTO
{
    public function __construct(
        public readonly string $title,
        #[ListOf('string')]
        public readonly array $keywords,
        #[ListOf(TagDTO::class)]
        public readonly array $tags,
    ) {}
}

$data = [
    'title' => 'Hydra intro',
    'keywords' => ['php', 'hydration', 'serialization'],
    'tags' => [
        ['name' => 'PHP', 'color' => '#8892BF'],
        ['name' => 'Library', 'color' => '#4CAF50'],
    ],
];

section('Input');
input_dump($data);
section('Hydrate');
$dto = $hydrator->hydrate($data, ArticleDTO::class);
echo "title: {$dto->title}\n";
echo 'keywords: ' . implode(', ', $dto->keywords) . "\n";
echo 'tags: ' . implode(', ', array_map(fn ($t) => "{$t->name} ({$t->color})", $dto->tags)) . "\n";

section('Dehydrate');
dump($dehydrator->dehydrate($dto));

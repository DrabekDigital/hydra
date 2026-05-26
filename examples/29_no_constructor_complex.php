<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\ArrayOf;
use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Attributes\NamingStrategy;
use DrabekDigital\Hydra\Attributes\Rename;
use DrabekDigital\Hydra\Attributes\Skip;
use DrabekDigital\Hydra\NamingStrategies\UnderscoreCase;

// ─────────────────────────────────────────────────────────────────────────────
// DTOs without constructors — Hydra assigns public properties directly.
//
// This example combines NamingStrategy, nested no-constructor objects,
// ListOf / ArrayOf collections, Rename with asymmetric keys, and Skip.
// ─────────────────────────────────────────────────────────────────────────────

class PostTag
{
    public string $name;
    public string $slug;
}

#[NamingStrategy(new UnderscoreCase())]
class PostAuthor
{
    public string $displayName;     // input/output key: display_name
    public ?string $bio = null;
    public string $role = 'author'; // defaults to 'author' when key absent
}

#[NamingStrategy(new UnderscoreCase())]
class BlogPost
{
    public string $title;
    public ?string $subtitle = null;
    public string $status = 'draft';
    public int $viewCount = 0;      // input/output key: view_count

    /**
     * Hydrated from input, but never written to dehydrated output.
     * Input key: internal_id (via NamingStrategy).
     */
    #[Skip(hydration: false, dehydration: true)]
    public ?string $internalId = null;

    /**
     * Input key is overridden to created_by.
     * Output key stays as author (NamingStrategy on property name).
     */
    #[Rename(from: 'created_by')]
    public PostAuthor $author;

    /** @var list<PostTag> */
    #[ListOf(PostTag::class)]
    public array $tags = [];

    /** @var list<string> */
    #[ArrayOf('string')]
    public array $categories = [];
}

// ─────────────────────────────────────────────────────────────────────────────
// Hydration — full input
// ─────────────────────────────────────────────────────────────────────────────

$input = [
    'title'       => 'Hydra — complex no-constructor example',
    'subtitle'    => 'All features, no constructor',
    'status'      => 'published',
    'view_count'  => 1_234,
    'internal_id' => 'sys-9f3a',
    'created_by'  => [
        'display_name' => 'Jan Novák',
        'bio'          => 'Senior PHP developer',
        'role'         => 'editor',
    ],
    'tags'        => [
        ['name' => 'PHP',        'slug' => 'php'],
        ['name' => 'Open Source','slug' => 'open-source'],
    ],
    'categories'  => ['backend', 'tooling'],
];

section('Input');
input_dump($input);

section('Hydrate');
$post = $hydrator->hydrate($input, BlogPost::class);

echo "title:      {$post->title}\n";
echo "subtitle:   {$post->subtitle}\n";
echo "status:     {$post->status}\n";
echo "viewCount:  {$post->viewCount}\n";
echo "internalId: {$post->internalId} (hydrated from internal_id)\n";
echo "author:     {$post->author->displayName} [{$post->author->role}]\n";
echo "tags:       " . implode(', ', array_map(fn ($t) => $t->name, $post->tags)) . "\n";
echo "categories: " . implode(', ', $post->categories) . "\n";

// ─────────────────────────────────────────────────────────────────────────────
// Hydration — minimal input (defaults kick in)
// ─────────────────────────────────────────────────────────────────────────────

$minimal = [
    'title'      => 'Minimal post',
    'created_by' => ['display_name' => 'Ghost'],
];

section('Hydrate (minimal — defaults)');
$mini = $hydrator->hydrate($minimal, BlogPost::class);

echo "status:    {$mini->status} (PHP default)\n";
echo "viewCount: {$mini->viewCount} (PHP default)\n";
echo "author role: {$mini->author->role} (PHP default on PostAuthor)\n";
echo "tags count: " . count($mini->tags) . "\n";

// ─────────────────────────────────────────────────────────────────────────────
// Dehydration
// ─────────────────────────────────────────────────────────────────────────────

section('Dehydrate');
$out = $dehydrator->dehydrate($post);
dump($out);

echo "internal_id present in output: " . (array_key_exists('internal_id', $out) ? 'yes' : 'no') . "\n";
echo "author key in output: " . (array_key_exists('author', $out) ? 'author' : '?') . " (not created_by)\n";

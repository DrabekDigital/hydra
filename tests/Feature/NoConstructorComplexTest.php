<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Tests\Fixtures\Dto\BlogPostDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\PostAuthorDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\PostTagDTO;
use DrabekDigital\Hydra\Tests\TestCase;

/**
 * Feature tests for a complex no-constructor DTO that combines:
 * - NamingStrategy (snake_case) on class and nested class
 * - Nested no-constructor object
 * - ListOf no-constructor items
 * - ArrayOf scalars
 * - Rename(from:) with asymmetric input/output key
 * - Skip(hydration: false, dehydration: true)
 * - PHP-level property defaults
 *
 * @internal
 *
 * @coversNothing
 */
class NoConstructorComplexTest extends TestCase
{
    // ── hydration ─────────────────────────────────────────────────────

    public function testHydratesFullBlogPost(): void
    {
        $dto = $this->hydrator->hydrate($this->fullInput(), BlogPostDTO::class);

        $this->assertSame('Hello World', $dto->title);
        $this->assertSame('A subtitle', $dto->subtitle);
        $this->assertSame('published', $dto->status);
        $this->assertSame(42, $dto->viewCount);
        $this->assertSame('int-abc-123', $dto->internalId);

        $this->assertInstanceOf(PostAuthorDTO::class, $dto->author);
        $this->assertSame('Jan Novák', $dto->author->displayName);
        $this->assertSame('Writer', $dto->author->bio);
        $this->assertSame('editor', $dto->author->role);

        $this->assertCount(2, $dto->tags);
        $this->assertInstanceOf(PostTagDTO::class, $dto->tags[0]);
        $this->assertSame('PHP', $dto->tags[0]->name);
        $this->assertSame('php', $dto->tags[0]->slug);
        $this->assertSame('Open Source', $dto->tags[1]->name);

        $this->assertSame(['technology', 'programming'], $dto->categories);
    }

    public function testHydratesWithPhpDefaults(): void
    {
        $dto = $this->hydrator->hydrate([
            'title' => 'Minimal Post',
            'created_by' => ['display_name' => 'Author'],
        ], BlogPostDTO::class);

        $this->assertSame('Minimal Post', $dto->title);
        $this->assertNull($dto->subtitle);
        $this->assertSame('draft', $dto->status);
        $this->assertSame(0, $dto->viewCount);
        $this->assertNull($dto->internalId);
        $this->assertSame([], $dto->tags);
        $this->assertSame([], $dto->categories);
    }

    public function testNestedAuthorUsesOwnNamingStrategy(): void
    {
        $dto = $this->hydrator->hydrate([
            'title' => 'Post',
            'created_by' => [
                'display_name' => 'Jane Doe',
                'bio' => 'Editor',
            ],
        ], BlogPostDTO::class);

        $this->assertSame('Jane Doe', $dto->author->displayName);
        $this->assertSame('Editor', $dto->author->bio);
    }

    public function testNestedAuthorHasPhpDefaultForRole(): void
    {
        $dto = $this->hydrator->hydrate([
            'title' => 'Post',
            'created_by' => ['display_name' => 'Someone'],
        ], BlogPostDTO::class);

        $this->assertSame('author', $dto->author->role); // PHP default
    }

    public function testRenameFromOverridesAuthorInputKey(): void
    {
        // Input must use created_by (not author) due to Rename(from: 'created_by')
        $dto = $this->hydrator->hydrate([
            'title' => 'Post',
            'created_by' => ['display_name' => 'Writer'],
        ], BlogPostDTO::class);

        $this->assertSame('Writer', $dto->author->displayName);
    }

    public function testSkipHydrationFalseStillHydratesInternalId(): void
    {
        $dto = $this->hydrator->hydrate([
            'title' => 'Post',
            'created_by' => ['display_name' => 'A'],
            'internal_id' => 'secret-123',
        ], BlogPostDTO::class);

        $this->assertSame('secret-123', $dto->internalId);
    }

    public function testMissingTitleFailsWithMissingKey(): void
    {
        $this->assertHydrationFails(
            ['created_by' => ['display_name' => 'A']],
            BlogPostDTO::class,
            'missing_key',
            'title'
        );
    }

    public function testMissingAuthorFailsWithMissingKey(): void
    {
        // Error key is the property name (author), not the renamed input key (created_by)
        $this->assertHydrationFails(
            ['title' => 'Post'],
            BlogPostDTO::class,
            'missing_key',
            'author'
        );
    }

    // ── dehydration ───────────────────────────────────────────────────

    public function testDehydratesWithSnakeCaseKeys(): void
    {
        $dto = $this->buildPost();
        $result = $this->dehydrator->dehydrate($dto);

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('subtitle', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('view_count', $result);
        $this->assertArrayHasKey('author', $result);
        $this->assertArrayHasKey('tags', $result);
        $this->assertArrayHasKey('categories', $result);
    }

    public function testDehydrationSkipsInternalId(): void
    {
        $dto = $this->buildPost();
        $result = $this->dehydrator->dehydrate($dto);

        $this->assertArrayNotHasKey('internal_id', $result);
        $this->assertArrayNotHasKey('internalId', $result);
    }

    public function testDehydratedAuthorUsesPropertyNameAsKey(): void
    {
        // Rename(from: 'created_by') only overrides the INPUT key.
        // Output key follows NamingStrategy: author → author.
        $dto = $this->buildPost();
        $result = $this->dehydrator->dehydrate($dto);

        $this->assertArrayHasKey('author', $result);
        $this->assertArrayNotHasKey('created_by', $result);
        $author = $result['author'];
        $this->assertIsArray($author);
        $this->assertSame('Jan Novák', $author['display_name']);
    }

    public function testDehydratedNestedAuthorHasSnakeCaseKeys(): void
    {
        $dto = $this->buildPost();
        $result = $this->dehydrator->dehydrate($dto);

        $author = $result['author'];
        $this->assertIsArray($author);
        $this->assertArrayHasKey('display_name', $author);
        $this->assertArrayHasKey('bio', $author);
        $this->assertArrayHasKey('role', $author);
        $this->assertArrayNotHasKey('displayName', $author);
    }

    public function testDehydratedTagsAreSerializedCorrectly(): void
    {
        $dto = $this->buildPost();
        $result = $this->dehydrator->dehydrate($dto);

        $tags = $result['tags'];
        $this->assertIsArray($tags);
        $this->assertCount(2, $tags);
        $first = $tags[0];
        $this->assertIsArray($first);
        $this->assertSame('PHP', $first['name']);
        $this->assertSame('php', $first['slug']);
    }

    public function testDehydratedCategoriesPreserveScalars(): void
    {
        $dto = $this->buildPost();
        $result = $this->dehydrator->dehydrate($dto);

        $this->assertSame(['tech', 'php'], $result['categories']);
    }

    // ── helpers ───────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function fullInput(): array
    {
        return [
            'title' => 'Hello World',
            'subtitle' => 'A subtitle',
            'status' => 'published',
            'view_count' => 42,
            'internal_id' => 'int-abc-123',
            'created_by' => [
                'display_name' => 'Jan Novák',
                'bio' => 'Writer',
                'role' => 'editor',
            ],
            'tags' => [
                ['name' => 'PHP', 'slug' => 'php'],
                ['name' => 'Open Source', 'slug' => 'open-source'],
            ],
            'categories' => ['technology', 'programming'],
        ];
    }

    private function buildPost(): BlogPostDTO
    {
        $author = new PostAuthorDTO();
        $author->displayName = 'Jan Novák';
        $author->bio = 'Writer';
        $author->role = 'author';

        $tag1 = new PostTagDTO();
        $tag1->name = 'PHP';
        $tag1->slug = 'php';

        $tag2 = new PostTagDTO();
        $tag2->name = 'Open Source';
        $tag2->slug = 'open-source';

        $post = new BlogPostDTO();
        $post->title = 'Hello World';
        $post->subtitle = 'A subtitle';
        $post->status = 'published';
        $post->viewCount = 100;
        $post->internalId = 'secret';
        $post->author = $author;
        $post->tags = [$tag1, $tag2];
        $post->categories = ['tech', 'php'];

        return $post;
    }
}

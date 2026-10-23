<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\CodeGen\DehydratorGenerator;
use DrabekDigital\Hydra\CodeGen\HydratorGenerator;
use DrabekDigital\Hydra\Dehydrator;
use DrabekDigital\Hydra\Exceptions\HydrationFailure;
use DrabekDigital\Hydra\Exceptions\ValidationFailure;
use DrabekDigital\Hydra\Hydrator;
use DrabekDigital\Hydra\Metadata\ClassAnalyzer;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\RecursiveBrokenNodeDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\RecursiveCategoryDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\RecursiveCommentDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\RecursiveFileDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\RecursiveFolderDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\RecursiveMapNodeDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\RecursiveNodeDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\RecursiveReplyDTO;
use DrabekDigital\Hydra\Tests\TestCase;
use DrabekDigital\Hydra\Validator;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Self-referencing types (trees) and indirect type cycles.
 *
 * @internal
 *
 * @coversNothing
 */
class RecursiveTypeTest extends TestCase
{
    /**
     * @param array<string, mixed> $data
     * @param class-string         $class
     */
    #[DataProvider('recursiveTreeProvider')]
    public function testRecursiveTypeRoundtrips(array $data, string $class): void
    {
        $this->assertRoundtrip($data, $class);
    }

    /**
     * @param array<string, mixed> $data
     * @param class-string         $class
     */
    #[DataProvider('recursiveTreeProvider')]
    public function testRecursiveTypeRoundtripsWithBypassCache(array $data, string $class): void
    {
        $object = $this->hydrator->hydrate($data, $class, bypassCache: true);
        $this->assertEquals($data, $this->dehydrator->dehydrate($object, bypassCache: true));

        $object = $this->hydrator->hydrate($data, $class, bypassCache: true);
        $this->assertEquals($data, $this->dehydrator->dehydrate($object, bypassCache: true));
    }

    /**
     * @return array<string, array{array<string, mixed>, class-string}>
     */
    public static function recursiveTreeProvider(): array
    {
        return [
            'ListOf self' => [self::nodeTree(), RecursiveNodeDTO::class],
            'nullable self' => [
                ['name' => 'leaf', 'parent' => ['name' => 'mid', 'parent' => ['name' => 'root', 'parent' => null]]],
                RecursiveCategoryDTO::class,
            ],
            'MapOf self' => [
                [
                    'name' => 'root',
                    'children' => [
                        'a' => ['name' => 'a', 'children' => ['b' => ['name' => 'b', 'children' => []]]],
                        'c' => ['name' => 'c', 'children' => []],
                    ],
                ],
                RecursiveMapNodeDTO::class,
            ],
            'indirect cycle' => [
                [
                    'text' => 'c1',
                    'replies' => [
                        [
                            'text' => 'r1',
                            'quoted' => ['text' => 'q', 'replies' => []],
                            'comments' => [
                                ['text' => 'c2', 'replies' => [['text' => 'r2', 'quoted' => null, 'comments' => []]]],
                            ],
                        ],
                    ],
                ],
                RecursiveCommentDTO::class,
            ],
            'discriminated self' => [self::folderTree(), RecursiveFolderDTO::class],
        ];
    }

    public function testHydratesListOfSelfSeveralLevelsDeep(): void
    {
        $this->assertHydrates(self::nodeTree(), RecursiveNodeDTO::class, function (RecursiveNodeDTO $root): void {
            $this->assertSame('root', $root->name);
            $this->assertCount(2, $root->children);
            $a = $root->children[0];
            $this->assertInstanceOf(RecursiveNodeDTO::class, $a);
            $this->assertSame('a', $a->name);
            $this->assertInstanceOf(RecursiveNodeDTO::class, $a->children[1]);
            $this->assertSame('a2', $a->children[1]->name);
            $this->assertInstanceOf(RecursiveNodeDTO::class, $a->children[1]->children[0]);
            $this->assertSame('a2x', $a->children[1]->children[0]->name);
            $this->assertSame([], $a->children[1]->children[0]->children);
        });
    }

    public function testHydratesNullableSelfReference(): void
    {
        $this->assertHydrates(
            ['name' => 'leaf', 'parent' => ['name' => 'root']],
            RecursiveCategoryDTO::class,
            function (RecursiveCategoryDTO $leaf): void {
                $this->assertInstanceOf(RecursiveCategoryDTO::class, $leaf->parent);
                $this->assertSame('root', $leaf->parent->name);
                $this->assertNull($leaf->parent->parent);
            }
        );
    }

    public function testHydratesIndirectCycle(): void
    {
        $this->assertHydrates(
            ['text' => 'c1', 'replies' => [['text' => 'r1', 'quoted' => ['text' => 'q'], 'comments' => [['text' => 'c2']]]]],
            RecursiveCommentDTO::class,
            function (RecursiveCommentDTO $comment): void {
                $reply = $comment->replies[0];
                $this->assertInstanceOf(RecursiveReplyDTO::class, $reply);
                $this->assertInstanceOf(RecursiveCommentDTO::class, $reply->quoted);
                $this->assertSame('q', $reply->quoted->text);
                $this->assertInstanceOf(RecursiveCommentDTO::class, $reply->comments[0]);
                $this->assertSame('c2', $reply->comments[0]->text);
            }
        );
    }

    public function testHydratesDiscriminatedSelfReference(): void
    {
        $this->assertHydrates(self::folderTree(), RecursiveFolderDTO::class, function (RecursiveFolderDTO $root): void {
            $this->assertInstanceOf(RecursiveFolderDTO::class, $root->children[0]);
            $this->assertInstanceOf(RecursiveFileDTO::class, $root->children[1]);
            $this->assertInstanceOf(RecursiveFolderDTO::class, $root->pinned);
            $nested = $root->children[0];
            $this->assertInstanceOf(RecursiveFileDTO::class, $nested->children[0]);
            $this->assertSame(10, $nested->children[0]->size);
        });
    }

    public function testDehydratesListOfSelf(): void
    {
        $tree = new RecursiveNodeDTO('root', [
            new RecursiveNodeDTO('a', [new RecursiveNodeDTO('a1')]),
            new RecursiveNodeDTO('b'),
        ]);

        $this->assertDehydrates($tree, [
            'name' => 'root',
            'children' => [
                ['name' => 'a', 'children' => [['name' => 'a1', 'children' => []]]],
                ['name' => 'b', 'children' => []],
            ],
        ]);
    }

    public function testDeepErrorHasFullPath(): void
    {
        $this->assertHydrationFailsAt(self::nodeTree(a2Name: 42), ['children[0].children[1].name'], false);
    }

    public function testDeepErrorsAreAggregatedWithBypassCache(): void
    {
        $this->assertHydrationFailsAt(
            self::nodeTree(a2Name: 42, bName: null),
            ['children[0].children[1].name', 'children[1].name'],
            true
        );
    }

    public function testDeepErrorInIndirectCycleHasFullPath(): void
    {
        try {
            $this->hydrator->hydrate(
                ['text' => 'c1', 'replies' => [['text' => 'r1', 'comments' => [['text' => 'ok'], ['text' => 5]]]]],
                RecursiveCommentDTO::class
            );
            $this->fail('Expected HydrationFailure');
        } catch (HydrationFailure $e) {
            $this->assertSame(
                ['replies[0].comments[1].text'],
                array_map(static fn ($error) => $error->fullPath, $e->getErrors())
            );
        }
    }

    public function testHydrateArrayWithRecursiveItemClass(): void
    {
        $data = [self::nodeTree(), ['name' => 'solo', 'children' => []]];

        foreach ([false, true] as $bypassCache) {
            $nodes = $this->hydrator->hydrateArray($data, className: RecursiveNodeDTO::class, bypassCache: $bypassCache);
            $this->assertCount(2, $nodes);
            $this->assertContainsOnlyInstancesOf(RecursiveNodeDTO::class, $nodes);
            $this->assertEquals($data, $this->dehydrator->dehydrateArray($nodes, bypassCache: $bypassCache));
        }
    }

    public function testDiscriminatedHydrateArrayWithRecursiveMappedClass(): void
    {
        $data = [['kind' => 'folder'] + self::folderTree(), ['kind' => 'file', 'name' => 'f', 'size' => 1]];
        $mapping = ['folder' => RecursiveFolderDTO::class, 'file' => RecursiveFileDTO::class];

        foreach ([false, true] as $bypassCache) {
            $items = $this->hydrator->hydrateArray($data, discriminator: 'kind', mapping: $mapping, bypassCache: $bypassCache);
            $this->assertInstanceOf(RecursiveFolderDTO::class, $items[0]);
            $this->assertInstanceOf(RecursiveFileDTO::class, $items[1]);
            $this->assertEquals(self::folderTree(), $this->dehydrator->dehydrate($items[0], $bypassCache));
        }
    }

    public function testBypassCacheGeneratesEachClassOncePerCall(): void
    {
        $hydratorGenerator = new class(new ClassAnalyzer()) extends HydratorGenerator {
            /** @var array<string, int> */
            public array $generated = [];

            public function generate(string $className): string
            {
                $this->generated[$className] = ($this->generated[$className] ?? 0) + 1;

                return parent::generate($className);
            }
        };
        $dehydratorGenerator = new class(new ClassAnalyzer()) extends DehydratorGenerator {
            /** @var array<string, int> */
            public array $generated = [];

            public function generate(string $className): string
            {
                $this->generated[$className] = ($this->generated[$className] ?? 0) + 1;

                return parent::generate($className);
            }
        };
        $hydrator = new Hydrator($this->cache, generator: $hydratorGenerator);
        $dehydrator = new Dehydrator($this->cache, generator: $dehydratorGenerator);
        $data = ['text' => 'c1', 'replies' => [['text' => 'r1', 'quoted' => null, 'comments' => []]]];

        $comment = $hydrator->hydrate($data, RecursiveCommentDTO::class, bypassCache: true);
        $dehydrator->dehydrate($comment, bypassCache: true);
        $expected = [RecursiveCommentDTO::class => 1, RecursiveReplyDTO::class => 1];
        $this->assertSame($expected, $hydratorGenerator->generated);
        $this->assertSame($expected, $dehydratorGenerator->generated);

        $hydrator->hydrate($data, RecursiveCommentDTO::class, bypassCache: true);
        $this->assertSame([RecursiveCommentDTO::class => 2, RecursiveReplyDTO::class => 2], $hydratorGenerator->generated);
    }

    public function testFailedNestedWiringIsNotServedFromMemory(): void
    {
        // RecursiveBrokenNodeDTO itself generates fine, its nested type does not; a half-wired runtime must not be reused
        for ($i = 0; $i < 2; ++$i) {
            try {
                $this->hydrator->hydrate(['name' => 'x'], RecursiveBrokenNodeDTO::class);
                $this->fail('Expected InvalidArgumentException');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('does not exist', $e->getMessage());
            }

            try {
                $this->dehydrator->dehydrate(new RecursiveBrokenNodeDTO('x'));
                $this->fail('Expected InvalidArgumentException');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('does not exist', $e->getMessage());
            }
        }
    }

    public function testClearCacheWithRecursiveTypes(): void
    {
        $data = self::nodeTree();
        $this->assertRoundtrip($data, RecursiveNodeDTO::class);

        $this->hydrator->clearCache(RecursiveNodeDTO::class);
        $this->dehydrator->clearCache(RecursiveNodeDTO::class);
        $this->assertRoundtrip($data, RecursiveNodeDTO::class);

        $this->hydrator->clearCache();
        $this->dehydrator->clearCache();
        $this->assertRoundtrip($data, RecursiveNodeDTO::class);
    }

    public function testValidatesTree(): void
    {
        $validator = new Validator();
        $valid = $this->hydrator->hydrate(self::nodeTree(), RecursiveNodeDTO::class);
        $validator->validate($valid);

        $invalid = $this->hydrator->hydrate(self::nodeTree(a2Name: ''), RecursiveNodeDTO::class);

        try {
            $validator->validate($invalid);
            $this->fail('Expected ValidationFailure');
        } catch (ValidationFailure $e) {
            $this->assertSame(
                ['children.0.children.1.name'],
                array_map(static fn ($error) => $error->fullPath, $e->getErrors())
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $expectedPaths
     */
    private function assertHydrationFailsAt(array $data, array $expectedPaths, bool $bypassCache): void
    {
        try {
            $this->hydrator->hydrate($data, RecursiveNodeDTO::class, $bypassCache);
            $this->fail('Expected HydrationFailure');
        } catch (HydrationFailure $e) {
            $paths = array_map(static fn ($error) => $error->fullPath, $e->getErrors());
            sort($paths);
            sort($expectedPaths);
            $this->assertSame($expectedPaths, $paths);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function nodeTree(mixed $a2Name = 'a2', mixed $bName = 'b'): array
    {
        return [
            'name' => 'root',
            'children' => [
                [
                    'name' => 'a',
                    'children' => [
                        ['name' => 'a1', 'children' => []],
                        ['name' => $a2Name, 'children' => [['name' => 'a2x', 'children' => []]]],
                    ],
                ],
                ['name' => $bName, 'children' => []],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function folderTree(): array
    {
        return [
            'name' => 'root',
            'children' => [
                [
                    'kind' => 'folder',
                    'name' => 'docs',
                    'children' => [['kind' => 'file', 'name' => 'a.txt', 'size' => 10]],
                    'pinned' => null,
                ],
                ['kind' => 'file', 'name' => 'b.txt', 'size' => 20],
            ],
            'pinned' => ['kind' => 'folder', 'name' => 'fav', 'children' => [], 'pinned' => null],
        ];
    }
}

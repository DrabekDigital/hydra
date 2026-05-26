<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\CodeGen;

use DrabekDigital\Hydra\CodeGen\Ast\Ast;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;

/**
 * Generates dehydrators for arrays of objects.
 */
class ArrayDehydratorGenerator
{
    private readonly PrettyPrinter $printer;

    public function __construct()
    {
        $this->printer = new PrettyPrinter(['shortArraySyntax' => true]);
    }

    /**
     * Generate a dehydrator for an array of objects.
     *
     * @param class-string[] $possibleClasses Classes that might be in the array
     */
    public function generate(array $possibleClasses): string
    {
        $stmts = [
            ...$this->generateUseStatements($possibleClasses),
            $this->generateReturnAnonymousClass($possibleClasses),
        ];

        return "<?php declare(strict_types=1);\n\n".$this->printer->prettyPrint($stmts);
    }

    /**
     * @param class-string[] $classes
     *
     * @return Stmt[]
     */
    private function generateUseStatements(array $classes): array
    {
        $stmts = [
            Ast::use('DrabekDigital\Hydra\Errors\DehydrationError'),
            Ast::use('DrabekDigital\Hydra\Errors\DehydrationErrorCode'),
            Ast::use('DrabekDigital\Hydra\Exceptions\DehydrationFailure'),
            Ast::use('DrabekDigital\Hydra\Runtime\ArrayDehydratorRuntime'),
        ];

        foreach ($classes as $class) {
            if (GenerationContext::typeIsNamespaced($class)) {
                $stmts[] = Ast::use($class);
            }
        }

        return $stmts;
    }

    /**
     * @param class-string[] $possibleClasses
     */
    private function generateReturnAnonymousClass(array $possibleClasses): Stmt\Return_
    {
        $classNode = new Stmt\Class_(null, [
            'implements' => [new Node\Name('ArrayDehydratorRuntime')],
            'stmts' => [
                $this->generateNestedDehydratorsProperty(),
                $this->generateSetNestedDehydratorMethod(),
                $this->generateDehydrateMethod($possibleClasses),
            ],
        ]);

        return Ast::return(new Node\Expr\New_($classNode));
    }

    private function generateNestedDehydratorsProperty(): Stmt\Property
    {
        return Ast::factory()->property('nestedDehydrators')
            ->makePrivate()
            ->setType('array')
            ->setDefault([])
            ->getNode()
        ;
    }

    private function generateSetNestedDehydratorMethod(): Stmt\ClassMethod
    {
        return Ast::factory()->method('setNestedDehydrator')
            ->makePublic()
            ->setReturnType('void')
            ->addParam(Ast::factory()->param('className')->setType('string'))
            ->addParam(Ast::factory()->param('dehydrator')->setType('object'))
            ->addStmt(
                Ast::expr(Ast::assign(
                    Ast::arrayAccess(
                        Ast::property(Ast::var('this'), 'nestedDehydrators'),
                        Ast::var('className')
                    ),
                    Ast::var('dehydrator')
                ))
            )
            ->getNode()
        ;
    }

    /**
     * @param class-string[] $possibleClasses
     */
    private function generateDehydrateMethod(array $possibleClasses): Stmt\ClassMethod
    {
        $stmts = [
            Ast::expr(Ast::assign(Ast::var('result'), Ast::array([]))),
            Ast::expr(Ast::assign(Ast::var('index'), Ast::int(0))),
        ];

        // Generate foreach loop with instanceof checks
        $stmts[] = $this->generateDehydrateLoop($possibleClasses);
        $stmts[] = Ast::return(Ast::var('result'));

        return Ast::factory()->method('dehydrate')
            ->makePublic()
            ->setReturnType('array')
            ->addParam(Ast::factory()->param('objects')->setType('array'))
            ->addParam(Ast::factory()->param('path')->setType('string')->setDefault(''))
            ->addStmts($stmts)
            ->getNode()
        ;
    }

    /**
     * @param class-string[] $possibleClasses
     */
    private function generateDehydrateLoop(array $possibleClasses): Stmt\Foreach_
    {
        // Build instanceof checks
        $ifChain = null;
        $lastElse = null;

        foreach (array_reverse($possibleClasses) as $class) {
            $shortName = GenerationContext::getShortName($class);

            $thenStmts = [
                Ast::expr(Ast::assign(
                    Ast::arrayAccess(Ast::var('result'), null),
                    Ast::methodCall(
                        Ast::arrayAccess(
                            Ast::property(Ast::var('this'), 'nestedDehydrators'),
                            $class
                        ),
                        'dehydrate',
                        [
                            Ast::var('object'),
                            Ast::ternary(
                                Ast::var('path'),
                                Ast::concat(Ast::var('path'), Ast::string('[$index]')),
                                Ast::concat(Ast::string('['), Ast::var('index'), Ast::string(']'))
                            ),
                        ]
                    )
                )),
            ];

            if ($ifChain === null) {
                // First (innermost) - this becomes the else of the previous
                $ifChain = Ast::if(
                    new Node\Expr\Instanceof_(Ast::var('object'), new Node\Name($shortName)),
                    $thenStmts
                );
            } else {
                // Wrap previous in else
                $newIf = Ast::if(
                    new Node\Expr\Instanceof_(Ast::var('object'), new Node\Name($shortName)),
                    $thenStmts,
                    [$ifChain]
                );
                $ifChain = $newIf;
            }
        }

        // Add final else for unknown types
        if ($ifChain !== null) {
            // We need to reconstruct with the unknown type else
            $unknownElse = [
                Ast::expr(Ast::assign(
                    Ast::arrayAccess(Ast::var('result'), null),
                    Ast::methodCall(
                        Ast::arrayAccess(
                            Ast::property(Ast::var('this'), 'nestedDehydrators'),
                            Ast::methodCall(Ast::var('object'), '__class')
                        ),
                        'dehydrate',
                        [
                            Ast::var('object'),
                            Ast::ternary(
                                Ast::var('path'),
                                Ast::concat(Ast::var('path'), Ast::string('[$index]')),
                                Ast::concat(Ast::string('['), Ast::var('index'), Ast::string(']'))
                            ),
                        ]
                    )
                )),
            ];
        }

        $loopBody = [
            $ifChain ?? Ast::expr(Ast::assign(
                Ast::arrayAccess(Ast::var('result'), null),
                Ast::methodCall(
                    Ast::arrayAccess(
                        Ast::property(Ast::var('this'), 'nestedDehydrators'),
                        Ast::staticCall('\get_class', '', [Ast::var('object')])
                    ),
                    'dehydrate',
                    [Ast::var('object'), Ast::var('path')]
                )
            )),
            Ast::expr(new Node\Expr\PostInc(Ast::var('index'))),
        ];

        return Ast::foreach(Ast::var('objects'), Ast::var('object'), $loopBody);
    }
}

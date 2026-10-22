<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\CodeGen;

use DrabekDigital\Hydra\CodeGen\Ast\Ast;
use DrabekDigital\Hydra\CodeGen\Ast\HydraAst;
use DrabekDigital\Hydra\Exceptions\HydrationFailure;
use DrabekDigital\Hydra\Runtime\ArrayHydratorRuntime;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;

/**
 * Generates hydrators for arrays of objects (with optional discriminator support).
 */
class ArrayHydratorGenerator
{
    private readonly PrettyPrinter $printer;

    public function __construct()
    {
        $this->printer = new PrettyPrinter(['shortArraySyntax' => true]);
    }

    /**
     * Generate a hydrator for an array of a single type.
     */
    public function generateSimple(string $itemClass): string
    {
        $stmts = [$this->generateReturnAnonymousClass($itemClass, null, null)];

        return "<?php declare(strict_types=1);\n\n".$this->printer->prettyPrint($stmts);
    }

    /**
     * Generate a hydrator for discriminated arrays.
     *
     * @param array<string, class-string> $mapping
     */
    public function generateDiscriminated(string $discriminator, array $mapping): string
    {
        $stmts = [$this->generateReturnAnonymousClass(null, $discriminator, $mapping)];

        return "<?php declare(strict_types=1);\n\n".$this->printer->prettyPrint($stmts);
    }

    /**
     * @param null|array<string, class-string> $mapping
     */
    private function generateReturnAnonymousClass(
        ?string $itemClass,
        ?string $discriminator,
        ?array $mapping,
    ): Stmt\Return_ {
        $classNode = new Stmt\Class_(null, [
            'implements' => [Ast::className(ArrayHydratorRuntime::class)],
            'stmts' => [
                $this->generateNestedHydratorsProperty(),
                $this->generateSetNestedHydratorMethod(),
                $this->generateHydrateMethod($itemClass, $discriminator, $mapping),
            ],
        ]);

        return Ast::return(new Node\Expr\New_($classNode));
    }

    private function generateNestedHydratorsProperty(): Stmt\Property
    {
        return Ast::factory()->property('nestedHydrators')
            ->makePrivate()
            ->setType('array')
            ->setDefault([])
            ->getNode()
        ;
    }

    private function generateSetNestedHydratorMethod(): Stmt\ClassMethod
    {
        return Ast::factory()->method('setNestedHydrator')
            ->makePublic()
            ->setReturnType('void')
            ->addParam(Ast::factory()->param('className')->setType('string'))
            ->addParam(Ast::factory()->param('hydrator')->setType('object'))
            ->addStmt(
                Ast::expr(Ast::assign(
                    Ast::arrayAccess(
                        Ast::property(Ast::var('this'), 'nestedHydrators'),
                        Ast::var('className')
                    ),
                    Ast::var('hydrator')
                ))
            )
            ->getNode()
        ;
    }

    /**
     * @param null|array<string, class-string> $mapping
     */
    private function generateHydrateMethod(
        ?string $itemClass,
        ?string $discriminator,
        ?array $mapping,
    ): Stmt\ClassMethod {
        $stmts = [
            Ast::expr(Ast::assign(Ast::var('errors'), Ast::array([]))),
            Ast::expr(Ast::assign(Ast::var('result'), Ast::array([]))),
            Ast::expr(Ast::assign(Ast::var('index'), Ast::int(0))),
        ];

        // Generate foreach loop
        if ($discriminator !== null && $mapping !== null) {
            $stmts[] = $this->generateDiscriminatedLoop($discriminator, $mapping);
        } else {
            $stmts[] = $this->generateSimpleLoop($itemClass);
        }

        // Throw if errors
        $stmts[] = HydraAst::throwIfErrors(HydrationFailure::class);
        $stmts[] = Ast::return(Ast::var('result'));

        return Ast::factory()->method('hydrate')
            ->makePublic()
            ->setReturnType('array')
            ->addParam(Ast::factory()->param('data')->setType('array'))
            ->addParam(Ast::factory()->param('path')->setType('string')->setDefault(''))
            ->addStmts($stmts)
            ->getNode()
        ;
    }

    private function generateSimpleLoop(?string $itemClass): Stmt\Foreach_
    {
        if ($itemClass === null) {
            throw new \InvalidArgumentException('Item class must be provided for simple array hydrator');
        }
        // foreach ($data as $item) {
        //     $itemPath = $path ? "$path[$index]" : "[$index]";
        //     if (!is_array($item)) { error }
        //     $result[] = $this->nestedHydrators['Class']->hydrate($item, $itemPath);
        //     $index++;
        // }

        $loopBody = [
            Ast::expr(Ast::assign(
                Ast::var('itemPath'),
                Ast::ternary(
                    Ast::var('path'),
                    Ast::concat(Ast::var('path'), Ast::string('[$index]')),
                    Ast::concat(Ast::string('['), Ast::var('index'), Ast::string(']'))
                )
            )),
            Ast::if(
                Ast::not(Ast::funcCall('is_array', [Ast::var('item')])),
                [
                    Ast::expr(Ast::assign(
                        Ast::arrayAccess(Ast::var('errors'), null),
                        HydraAst::hydrationError(
                            'TYPE_MISMATCH',
                            Ast::concat(
                                Ast::string('Expected array at index '),
                                Ast::var('index'),
                                Ast::string(', got '),
                                Ast::funcCall('gettype', [Ast::var('item')])
                            ),
                            Ast::funcCall('strval', [Ast::var('index')]),
                            Ast::var('itemPath')
                        )
                    )),
                ],
                [
                    Ast::expr(Ast::assign(
                        Ast::arrayAccess(Ast::var('result'), null),
                        Ast::methodCall(
                            Ast::arrayAccess(
                                Ast::property(Ast::var('this'), 'nestedHydrators'),
                                $itemClass
                            ),
                            'hydrate',
                            [Ast::var('item'), Ast::var('itemPath')]
                        )
                    )),
                ]
            ),
            Ast::expr(new Node\Expr\PostInc(Ast::var('index'))),
        ];

        return Ast::foreach(Ast::var('data'), Ast::var('item'), $loopBody);
    }

    /**
     * @param array<string, class-string> $mapping
     */
    private function generateDiscriminatedLoop(string $discriminator, array $mapping): Stmt\Foreach_
    {
        // Build match arms for discriminator
        $matchArms = [];
        foreach ($mapping as $value => $class) {
            $matchArms[] = new Node\MatchArm(
                [Ast::string($value)],
                Ast::methodCall(
                    Ast::arrayAccess(
                        Ast::property(Ast::var('this'), 'nestedHydrators'),
                        $class
                    ),
                    'hydrate',
                    [Ast::var('item'), Ast::var('itemPath')]
                )
            );
        }

        // Default arm - error
        $matchArms[] = new Node\MatchArm(
            null,
            Ast::null() // Will be handled by the if below
        );

        $loopBody = [
            Ast::expr(Ast::assign(
                Ast::var('itemPath'),
                Ast::ternary(
                    Ast::var('path'),
                    Ast::concat(Ast::var('path'), Ast::string('[$index]')),
                    Ast::concat(Ast::string('['), Ast::var('index'), Ast::string(']'))
                )
            )),
            Ast::if(
                Ast::not(Ast::funcCall('is_array', [Ast::var('item')])),
                [
                    Ast::expr(Ast::assign(
                        Ast::arrayAccess(Ast::var('errors'), null),
                        HydraAst::hydrationError(
                            'TYPE_MISMATCH',
                            Ast::concat(
                                Ast::string('Expected array at index '),
                                Ast::var('index'),
                                Ast::string(', got '),
                                Ast::funcCall('gettype', [Ast::var('item')])
                            ),
                            Ast::funcCall('strval', [Ast::var('index')]),
                            Ast::var('itemPath')
                        )
                    )),
                ],
                [
                    // Check discriminator exists
                    Ast::if(
                        Ast::not(Ast::funcCall('array_key_exists', [Ast::string($discriminator), Ast::var('item')])),
                        [
                            Ast::expr(Ast::assign(
                                Ast::arrayAccess(Ast::var('errors'), null),
                                HydraAst::hydrationError(
                                    'MISSING_KEY',
                                    Ast::string("Missing discriminator '{$discriminator}'"),
                                    Ast::string($discriminator),
                                    Ast::var('itemPath')
                                )
                            )),
                        ],
                        [
                            Ast::expr(Ast::assign(
                                Ast::var('discriminatorValue'),
                                Ast::arrayAccess(Ast::var('item'), $discriminator)
                            )),
                            Ast::expr(Ast::assign(Ast::var('itemPayload'), Ast::var('item'))),
                            new Stmt\Unset_([Ast::arrayAccess(Ast::var('itemPayload'), $discriminator)]),
                            $this->generateMatchSwitch($discriminator, $mapping),
                        ]
                    ),
                ]
            ),
            Ast::expr(new Node\Expr\PostInc(Ast::var('index'))),
        ];

        return Ast::foreach(Ast::var('data'), Ast::var('item'), $loopBody);
    }

    /**
     * @param array<string, class-string> $mapping
     */
    private function generateMatchSwitch(string $discriminator, array $mapping): Stmt
    {
        $cases = [];

        foreach ($mapping as $value => $class) {
            $cases[] = new Stmt\Case_(
                Ast::string($value),
                [
                    Ast::expr(Ast::assign(
                        Ast::arrayAccess(Ast::var('result'), null),
                        Ast::methodCall(
                            Ast::arrayAccess(
                                Ast::property(Ast::var('this'), 'nestedHydrators'),
                                $class
                            ),
                            'hydrate',
                            [Ast::var('itemPayload'), Ast::var('itemPath')]
                        )
                    )),
                    new Stmt\Break_(),
                ]
            );
        }

        // Default case - unknown discriminator
        $cases[] = new Stmt\Case_(
            null,
            [
                Ast::expr(Ast::assign(
                    Ast::arrayAccess(Ast::var('errors'), null),
                    HydraAst::hydrationError(
                        'INVALID_VALUE',
                        Ast::concat(
                            Ast::string("Unknown discriminator value for '{$discriminator}': "),
                            Ast::funcCall('json_encode', [Ast::var('discriminatorValue')])
                        ),
                        Ast::string($discriminator),
                        Ast::var('itemPath')
                    )
                )),
                new Stmt\Break_(),
            ]
        );

        return new Stmt\Switch_(Ast::var('discriminatorValue'), $cases);
    }
}

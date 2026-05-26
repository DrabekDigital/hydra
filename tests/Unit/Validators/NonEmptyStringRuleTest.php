<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Validators;

use DrabekDigital\Hydra\Validators\Rules\NonEmptyStringRule;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class NonEmptyStringRuleTest extends TestCase
{
    public function testNonEmptyStringPasses(): void
    {
        $rule = new NonEmptyStringRule();
        $this->assertEmpty($rule->validate('hello', 'x', 'x'));
    }

    public function testSingleSpacePasses(): void
    {
        $rule = new NonEmptyStringRule();
        $this->assertEmpty($rule->validate(' ', 'x', 'x'));
    }

    public function testEmptyStringFails(): void
    {
        $rule = new NonEmptyStringRule();
        $errors = $rule->validate('', 'x', 'x');
        $this->assertCount(1, $errors);
        $this->assertSame('too_short', $errors[0]->code->value);
    }

    public function testKeyAndPathArePreservedInError(): void
    {
        $rule = new NonEmptyStringRule();
        $errors = $rule->validate('', 'title', 'article.title');
        $this->assertSame('title', $errors[0]->key);
        $this->assertSame('article.title', $errors[0]->fullPath);
    }

    public function testZeroStringPasses(): void
    {
        // "0" is falsy in PHP but is not an empty string
        $rule = new NonEmptyStringRule();
        $this->assertEmpty($rule->validate('0', 'x', 'x'));
    }

    public function testNewlinePasses(): void
    {
        $rule = new NonEmptyStringRule();
        $this->assertEmpty($rule->validate("\n", 'x', 'x'));
    }

    public function testNullByteStringPasses(): void
    {
        $rule = new NonEmptyStringRule();
        $this->assertEmpty($rule->validate("\0", 'x', 'x'));
    }
}

<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Validators;

use DrabekDigital\Hydra\Attributes\Validation\StringMatches;
use DrabekDigital\Hydra\Validators\Rules\StringMatchesRule;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class StringMatchesRuleTest extends TestCase
{
    public function testMatchingStringPasses(): void
    {
        $rule = new StringMatchesRule(new StringMatches('/^\d+$/'));
        $this->assertEmpty($rule->validate('12345', 'x', 'x'));
    }

    public function testNonMatchingStringFails(): void
    {
        $rule = new StringMatchesRule(new StringMatches('/^\d+$/'));
        $errors = $rule->validate('abc', 'x', 'x');
        $this->assertCount(1, $errors);
        $this->assertSame('pattern_mismatch', $errors[0]->code->value);
    }

    public function testDefaultMessageUsedWhenNoneProvided(): void
    {
        $rule = new StringMatchesRule(new StringMatches('/^\d+$/'));
        $errors = $rule->validate('bad', 'code', 'code');
        $this->assertStringContainsString('code', $errors[0]->programmerMessage);
    }

    public function testCustomMessageIsUsed(): void
    {
        $rule = new StringMatchesRule(new StringMatches('/^\d+$/', message: 'Must be numeric'));
        $errors = $rule->validate('bad', 'x', 'x');
        $this->assertSame('Must be numeric', $errors[0]->programmerMessage);
    }

    public function testInvalidPatternDoesNotThrow(): void
    {
        // @preg_match suppresses warnings — invalid pattern returns false, treated as non-match
        $rule = new StringMatchesRule(new StringMatches('not_a_pattern'));
        $errors = $rule->validate('anything', 'x', 'x');
        $this->assertCount(1, $errors);
    }

    public function testKeyAndPathArePreservedInError(): void
    {
        $rule = new StringMatchesRule(new StringMatches('/^\d+$/'));
        $errors = $rule->validate('bad', 'slug', 'item.slug');
        $this->assertSame('slug', $errors[0]->key);
        $this->assertSame('item.slug', $errors[0]->fullPath);
    }

    public function testEmptyStringMatchesWildcardPattern(): void
    {
        $rule = new StringMatchesRule(new StringMatches('/^.*$/'));
        $this->assertEmpty($rule->validate('', 'x', 'x'));
    }

    public function testEmptyStringFailsNonEmptyPattern(): void
    {
        $rule = new StringMatchesRule(new StringMatches('/^.+$/'));
        $errors = $rule->validate('', 'x', 'x');
        $this->assertCount(1, $errors);
    }

    public function testCaseInsensitiveFlagWorks(): void
    {
        $rule = new StringMatchesRule(new StringMatches('/^hello$/i'));
        $this->assertEmpty($rule->validate('HELLO', 'x', 'x'));
        $this->assertEmpty($rule->validate('Hello', 'x', 'x'));
        $this->assertCount(1, $rule->validate('world', 'x', 'x'));
    }

    public function testUnicodePatternMatchesMultibyteString(): void
    {
        $rule = new StringMatchesRule(new StringMatches('/^\p{L}+$/u'));
        $this->assertEmpty($rule->validate('héllo', 'x', 'x'));
        $this->assertCount(1, $rule->validate('héllo123', 'x', 'x'));
    }

    public function testPatternMatchedAgainstFullString(): void
    {
        // /\d+/ without anchors matches "abc123def"
        $rule = new StringMatchesRule(new StringMatches('/\d+/'));
        $this->assertEmpty($rule->validate('abc123def', 'x', 'x'));
        $this->assertCount(1, $rule->validate('abcdef', 'x', 'x'));
    }
}

<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Validators;

use DrabekDigital\Hydra\Attributes\Validation\Choice;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Priority;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Status;
use DrabekDigital\Hydra\Validators\Rules\ChoiceRule;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ChoiceRuleTest extends TestCase
{
    public function testAllowedChoicePasses(): void
    {
        $rule = new ChoiceRule(new Choice(['red', 'green']));
        $this->assertEmpty($rule->validate('red', 'color', 'color'));
    }

    public function testDisallowedChoiceFails(): void
    {
        $rule = new ChoiceRule(new Choice(['red', 'green']));
        $errors = $rule->validate('blue', 'color', 'payload.color');
        $this->assertCount(1, $errors);
        $this->assertSame('invalid_choice', $errors[0]->code->value);
        $this->assertSame('color', $errors[0]->key);
        $this->assertSame('payload.color', $errors[0]->fullPath);
    }

    public function testNonStrictModeAcceptsEquivalentValue(): void
    {
        $rule = new ChoiceRule(new Choice([1, 2, 3], strict: false));
        $this->assertEmpty($rule->validate('1', 'number', 'number'));
    }

    public function testEnumClassAcceptsBackedValue(): void
    {
        $rule = new ChoiceRule(new Choice(enumClass: Status::class));
        $this->assertEmpty($rule->validate('published', 'status', 'status'));
    }

    public function testEnumClassAcceptsEnumCaseInstance(): void
    {
        $rule = new ChoiceRule(new Choice(enumClass: Priority::class));
        $this->assertEmpty($rule->validate(Priority::High, 'priority', 'priority'));
    }

    public function testEnumCaseChoicesAcceptBackedEnumValue(): void
    {
        $rule = new ChoiceRule(new Choice([Status::Draft, Status::Published]));
        $this->assertEmpty($rule->validate('draft', 'status', 'status'));
    }

    public function testEnumClassRejectsUnknownValue(): void
    {
        $rule = new ChoiceRule(new Choice(enumClass: Status::class));
        $errors = $rule->validate('unknown', 'status', 'payload.status');
        $this->assertCount(1, $errors);
        $this->assertSame('invalid_choice', $errors[0]->code->value);
    }
}

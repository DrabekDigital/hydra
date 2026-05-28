<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Validators;

use DrabekDigital\Hydra\Attributes\Validation\IsNull;
use DrabekDigital\Hydra\Validators\Rules\NullRule;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class NullRuleTest extends TestCase
{
    public function testNullValuePasses(): void
    {
        $rule = new NullRule(new IsNull());
        $this->assertEmpty($rule->validate(null, 'value', 'value'));
    }

    public function testNonNullValueFails(): void
    {
        $rule = new NullRule(new IsNull());
        $errors = $rule->validate('x', 'value', 'payload.value');
        $this->assertCount(1, $errors);
        $this->assertSame('must_be_null', $errors[0]->code->value);
        $this->assertSame('value', $errors[0]->key);
        $this->assertSame('payload.value', $errors[0]->fullPath);
    }
}

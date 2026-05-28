<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Validators;

use DrabekDigital\Hydra\Attributes\Validation\NotNull;
use DrabekDigital\Hydra\Validators\Rules\NotNullRule;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class NotNullRuleTest extends TestCase
{
    public function testNonNullValuePasses(): void
    {
        $rule = new NotNullRule(new NotNull());
        $this->assertEmpty($rule->validate('present', 'value', 'value'));
    }

    public function testNullValueFails(): void
    {
        $rule = new NotNullRule(new NotNull());
        $errors = $rule->validate(null, 'value', 'payload.value');
        $this->assertCount(1, $errors);
        $this->assertSame('not_null', $errors[0]->code->value);
        $this->assertSame('value', $errors[0]->key);
        $this->assertSame('payload.value', $errors[0]->fullPath);
    }
}

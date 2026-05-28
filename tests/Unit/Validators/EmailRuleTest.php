<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Validators;

use DrabekDigital\Hydra\Attributes\Validation\Email;
use DrabekDigital\Hydra\Validators\Rules\EmailRule;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class EmailRuleTest extends TestCase
{
    public function testValidEmailPasses(): void
    {
        $rule = new EmailRule(new Email());
        $this->assertEmpty($rule->validate('john@example.com', 'email', 'email'));
    }

    public function testInvalidEmailFails(): void
    {
        $rule = new EmailRule(new Email());
        $errors = $rule->validate('invalid-email', 'email', 'user.email');
        $this->assertCount(1, $errors);
        $this->assertSame('invalid_email', $errors[0]->code->value);
        $this->assertSame('email', $errors[0]->key);
        $this->assertSame('user.email', $errors[0]->fullPath);
    }
}

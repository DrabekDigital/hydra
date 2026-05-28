<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Validators;

use DrabekDigital\Hydra\Attributes\Validation\Uuid;
use DrabekDigital\Hydra\Validators\Rules\UuidRule;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class UuidRuleTest extends TestCase
{
    public function testValidUuidPasses(): void
    {
        $rule = new UuidRule(new Uuid());
        $this->assertEmpty($rule->validate('550e8400-e29b-41d4-a716-446655440000', 'uuid', 'uuid'));
    }

    public function testInvalidUuidFails(): void
    {
        $rule = new UuidRule(new Uuid());
        $errors = $rule->validate('not-a-uuid', 'uuid', 'entity.uuid');
        $this->assertCount(1, $errors);
        $this->assertSame('invalid_uuid', $errors[0]->code->value);
        $this->assertSame('uuid', $errors[0]->key);
        $this->assertSame('entity.uuid', $errors[0]->fullPath);
    }
}

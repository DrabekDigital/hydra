<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Validators;

use DrabekDigital\Hydra\Attributes\Validation\Count;
use DrabekDigital\Hydra\Attributes\Validation\Email;
use DrabekDigital\Hydra\Attributes\Validation\LengthRange;
use DrabekDigital\Hydra\Attributes\Validation\NotNull;
use DrabekDigital\Hydra\Attributes\Validation\NullValidationBehavior;
use DrabekDigital\Hydra\Attributes\Validation\NumberRange;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;
use DrabekDigital\Hydra\Validators\ValidationRuleApplicator;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ValidationRuleApplicatorTest extends TestCase
{
    public function testEmailRuleValidatesString(): void
    {
        $applicator = new ValidationRuleApplicator([]);
        $errors = $applicator->apply('email', 'user@example.com', 'email', [
            Email::class => new Email(),
        ]);

        $this->assertEmpty($errors);
    }

    public function testEmailRuleRejectsInvalidString(): void
    {
        $applicator = new ValidationRuleApplicator([]);
        $errors = $applicator->apply('email', 'not-an-email', 'email', [
            Email::class => new Email(),
        ]);

        $this->assertCount(1, $errors);
        $this->assertSame(ValidationErrorCode::INVALID_EMAIL, $errors[0]->code);
    }

    public function testEmailRuleReturnsRuleNotApplicableForIntValue(): void
    {
        $applicator = new ValidationRuleApplicator([]);
        $errors = $applicator->apply('email', 123, 'email', [
            Email::class => new Email(),
        ]);

        $this->assertCount(1, $errors);
        $this->assertSame(ValidationErrorCode::RULE_NOT_APPLICABLE, $errors[0]->code);
        $this->assertSame('email', $errors[0]->fullPath);
    }

    public function testLengthRangeSkipsWhenNullAndOnNullSkip(): void
    {
        $applicator = new ValidationRuleApplicator([]);
        $errors = $applicator->apply('name', null, 'name', [
            LengthRange::class => new LengthRange(min: 1, onNull: NullValidationBehavior::SkipWhenNull),
        ]);

        $this->assertEmpty($errors);
    }

    public function testLengthRangeFailsWhenNullAndOnNullFail(): void
    {
        $applicator = new ValidationRuleApplicator([]);
        $errors = $applicator->apply('name', null, 'name', [
            LengthRange::class => new LengthRange(min: 1, onNull: NullValidationBehavior::FailWhenNull),
        ]);

        $this->assertCount(1, $errors);
        $this->assertSame(ValidationErrorCode::NOT_NULL, $errors[0]->code);
    }

    public function testLengthRangeValidateAlwaysOnNullReturnsRuleNotApplicable(): void
    {
        $applicator = new ValidationRuleApplicator([]);
        $errors = $applicator->apply('name', null, 'name', [
            LengthRange::class => new LengthRange(min: 1, onNull: NullValidationBehavior::ValidateAlways),
        ]);

        $this->assertCount(1, $errors);
        $this->assertSame(ValidationErrorCode::RULE_NOT_APPLICABLE, $errors[0]->code);
    }

    public function testNumberRangeAppliesToInt(): void
    {
        $applicator = new ValidationRuleApplicator([]);
        $errors = $applicator->apply('age', 25, 'age', [
            NumberRange::class => new NumberRange(min: 18, max: 99),
        ]);

        $this->assertEmpty($errors);
    }

    public function testNumberRangeReturnsRuleNotApplicableForString(): void
    {
        $applicator = new ValidationRuleApplicator([]);
        $errors = $applicator->apply('age', '25', 'age', [
            NumberRange::class => new NumberRange(min: 18, max: 99),
        ]);

        $this->assertCount(1, $errors);
        $this->assertSame(ValidationErrorCode::RULE_NOT_APPLICABLE, $errors[0]->code);
    }

    public function testCountReturnsRuleNotApplicableForScalar(): void
    {
        $applicator = new ValidationRuleApplicator([]);
        $errors = $applicator->apply('items', 'not-array', 'items', [
            Count::class => new Count(min: 1),
        ]);

        $this->assertCount(1, $errors);
        $this->assertSame(ValidationErrorCode::RULE_NOT_APPLICABLE, $errors[0]->code);
    }

    public function testNotNullFailsForNullValue(): void
    {
        $applicator = new ValidationRuleApplicator([]);
        $errors = $applicator->apply('value', null, 'value', [
            NotNull::class => new NotNull(),
        ]);

        $this->assertCount(1, $errors);
        $this->assertSame(ValidationErrorCode::NOT_NULL, $errors[0]->code);
    }

    public function testMultipleTypedRulesCollectMultipleErrors(): void
    {
        $applicator = new ValidationRuleApplicator([]);
        $errors = $applicator->apply('email', 123, 'user.email', [
            Email::class => new Email(),
            NotNull::class => new NotNull(),
        ]);

        $this->assertGreaterThanOrEqual(1, count($errors));
        $codes = array_map(static fn ($e) => $e->code, $errors);
        $this->assertContains(ValidationErrorCode::RULE_NOT_APPLICABLE, $codes);
    }
}

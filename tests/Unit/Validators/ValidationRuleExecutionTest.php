<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Validators;

use DrabekDigital\Hydra\Attributes\Validation\Choice;
use DrabekDigital\Hydra\Attributes\Validation\Count;
use DrabekDigital\Hydra\Attributes\Validation\DateTimeRange;
use DrabekDigital\Hydra\Attributes\Validation\Email;
use DrabekDigital\Hydra\Attributes\Validation\IsNull;
use DrabekDigital\Hydra\Attributes\Validation\LengthRange;
use DrabekDigital\Hydra\Attributes\Validation\NotNull;
use DrabekDigital\Hydra\Attributes\Validation\NullValidationBehavior;
use DrabekDigital\Hydra\Attributes\Validation\NumberRange;
use DrabekDigital\Hydra\Attributes\Validation\UniqueItems;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Status;
use DrabekDigital\Hydra\Validators\ValidationRuleExecution;
use DrabekDigital\Hydra\Validators\ValidationRuleExecutionResult;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ValidationRuleExecutionTest extends TestCase
{
    public function testSkipWhenNullSkipsStringRule(): void
    {
        $attribute = new LengthRange(min: 1, onNull: NullValidationBehavior::SkipWhenNull);
        $this->assertSame(
            ValidationRuleExecutionResult::Skip,
            ValidationRuleExecution::resolve($attribute, null),
        );
    }

    public function testFailWhenNullFailsStringRule(): void
    {
        $attribute = new LengthRange(min: 1, onNull: NullValidationBehavior::FailWhenNull);
        $this->assertSame(
            ValidationRuleExecutionResult::NullFailure,
            ValidationRuleExecution::resolve($attribute, null),
        );
    }

    public function testValidateAlwaysOnStringRuleReturnsTypeMismatchForNull(): void
    {
        $attribute = new LengthRange(min: 1, onNull: NullValidationBehavior::ValidateAlways);
        $this->assertSame(
            ValidationRuleExecutionResult::TypeMismatch,
            ValidationRuleExecution::resolve($attribute, null),
        );
    }

    public function testTypeMismatchForIntAgainstEmail(): void
    {
        $attribute = new Email();
        $this->assertSame(
            ValidationRuleExecutionResult::TypeMismatch,
            ValidationRuleExecution::resolve($attribute, 123),
        );
    }

    public function testStringValueAppliesEmailRule(): void
    {
        $attribute = new Email();
        $this->assertSame(
            ValidationRuleExecutionResult::Apply,
            ValidationRuleExecution::resolve($attribute, 'a@b.c'),
        );
    }

    public function testNumberRangeAppliesToIntAndFloat(): void
    {
        $attribute = new NumberRange(min: 0, max: 100);
        $this->assertSame(ValidationRuleExecutionResult::Apply, ValidationRuleExecution::resolve($attribute, 42));
        $this->assertSame(ValidationRuleExecutionResult::Apply, ValidationRuleExecution::resolve($attribute, 3.14));
    }

    public function testNumberRangeTypeMismatchForString(): void
    {
        $attribute = new NumberRange(min: 0, max: 100);
        $this->assertSame(
            ValidationRuleExecutionResult::TypeMismatch,
            ValidationRuleExecution::resolve($attribute, '42'),
        );
    }

    public function testCountAppliesToArrayOnly(): void
    {
        $attribute = new Count(min: 1);
        $this->assertSame(ValidationRuleExecutionResult::Apply, ValidationRuleExecution::resolve($attribute, ['a']));
        $this->assertSame(ValidationRuleExecutionResult::TypeMismatch, ValidationRuleExecution::resolve($attribute, 'a'));
    }

    public function testDateTimeRangeAppliesToDateTimeInterface(): void
    {
        $attribute = new DateTimeRange();
        $this->assertSame(
            ValidationRuleExecutionResult::Apply,
            ValidationRuleExecution::resolve($attribute, new \DateTimeImmutable()),
        );
        $this->assertSame(
            ValidationRuleExecutionResult::TypeMismatch,
            ValidationRuleExecution::resolve($attribute, '2024-01-01'),
        );
    }

    public function testChoiceAppliesToEnumCaseAndBackedValue(): void
    {
        $attribute = new Choice(enumClass: Status::class);
        $this->assertSame(
            ValidationRuleExecutionResult::Apply,
            ValidationRuleExecution::resolve($attribute, Status::Draft),
        );
        $this->assertSame(
            ValidationRuleExecutionResult::Apply,
            ValidationRuleExecution::resolve($attribute, 'draft'),
        );
    }

    public function testNotNullAlwaysAppliesIncludingForNull(): void
    {
        $attribute = new NotNull();
        $this->assertSame(
            ValidationRuleExecutionResult::Apply,
            ValidationRuleExecution::resolve($attribute, null),
        );
    }

    public function testIsNullAlwaysAppliesIncludingForNull(): void
    {
        $attribute = new IsNull();
        $this->assertSame(
            ValidationRuleExecutionResult::Apply,
            ValidationRuleExecution::resolve($attribute, null),
        );
    }

    public function testUniqueItemsAppliesToArray(): void
    {
        $attribute = new UniqueItems();
        $this->assertSame(ValidationRuleExecutionResult::Apply, ValidationRuleExecution::resolve($attribute, [1, 2]));
    }

    public function testNullFailureErrorUsesNotNullCode(): void
    {
        $error = ValidationRuleExecution::nullFailureError(new LengthRange(min: 1, onNull: NullValidationBehavior::FailWhenNull), 'x', 'root.x');
        $this->assertSame(ValidationErrorCode::NOT_NULL, $error->code);
        $this->assertSame('x', $error->key);
        $this->assertStringContainsString('LengthRange', $error->programmerMessage);
    }

    public function testTypeMismatchErrorUsesRuleNotApplicableCode(): void
    {
        $error = ValidationRuleExecution::typeMismatchError('email', 'user.email', new Email());
        $this->assertSame(ValidationErrorCode::RULE_NOT_APPLICABLE, $error->code);
        $this->assertSame('user.email', $error->fullPath);
    }
}

<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Errors\ValidationErrorCode;
use DrabekDigital\Hydra\Exceptions\ValidationFailure;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Status;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationFailWhenNullLengthDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationIncompatibleAttributeDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationIncompatibleCountDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationIncompatibleNumberRangeDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationMixedPropertyEmailDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationNullableLengthDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationTypedRulesDTO;
use DrabekDigital\Hydra\Tests\TestCase;
use DrabekDigital\Hydra\Validator;

/**
 * @internal
 *
 * @coversNothing
 */
class ValidatorTypeEnforcementTest extends TestCase
{
    public function testCompatibleTypedRulesDtoPassesValidation(): void
    {
        $validator = new Validator();
        $dto = new ValidationTypedRulesDTO(
            name: 'hydra',
            age: 30,
            weight: 72.5,
            code: 'ABC',
            tags: ['one', 'two'],
            birthday: new \DateTimeImmutable('2000-06-15'),
            status: Status::Published,
        );

        $validator->validate($dto);
        $this->addToAssertionCount(1);
    }

    public function testCompatibleTypedRulesDtoCollectsValidationErrors(): void
    {
        $validator = new Validator();
        $dto = new ValidationTypedRulesDTO(
            name: 'x',
            age: 200,
            weight: 72.5,
            code: '',
            tags: ['dup', 'dup'],
            birthday: new \DateTimeImmutable('1999-01-01'),
            status: Status::Draft,
        );

        try {
            $validator->validate($dto);
            $this->fail('Expected ValidationFailure was not thrown');
        } catch (ValidationFailure $e) {
            $this->assertGreaterThanOrEqual(3, count($e->getErrors()));
        }
    }

    public function testEmailOnIntPropertyThrowsOnFirstValidate(): void
    {
        $validator = new Validator();
        $dto = new ValidationIncompatibleAttributeDTO(age: 30);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Email' on property 'age'");

        $validator->validate($dto);
    }

    public function testCountOnStringPropertyThrowsOnFirstValidate(): void
    {
        $validator = new Validator();
        $dto = new ValidationIncompatibleCountDTO(label: 'test');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Count' on property 'label'");

        $validator->validate($dto);
    }

    public function testNumberRangeOnStringPropertyThrowsOnFirstValidate(): void
    {
        $validator = new Validator();
        $dto = new ValidationIncompatibleNumberRangeDTO(quantity: '5');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("NumberRange' on property 'quantity'");

        $validator->validate($dto);
    }

    public function testMixedPropertyWithEmailThrowsOnFirstValidate(): void
    {
        $validator = new Validator();
        $dto = new ValidationMixedPropertyEmailDTO(contact: 'a@b.c');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('mixed');

        $validator->validate($dto);
    }

    public function testNullablePropertySkipsLengthRangeWhenNullAndOnNullSkip(): void
    {
        $validator = new Validator();
        $dto = new ValidationNullableLengthDTO(nickname: null);

        $validator->validate($dto);
        $this->addToAssertionCount(1);
    }

    public function testFailWhenNullLengthRangeRejectsNull(): void
    {
        $validator = new Validator();
        $dto = new ValidationFailWhenNullLengthDTO(nickname: null);

        try {
            $validator->validate($dto);
            $this->fail('Expected ValidationFailure was not thrown');
        } catch (ValidationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertSame(ValidationErrorCode::NOT_NULL, $errors[0]->code);
            $this->assertSame('nickname', $errors[0]->fullPath);
        }
    }

    public function testCompatibilityCheckRunsOnlyOncePerClass(): void
    {
        $validator = new Validator();
        $dto = new ValidationNullableLengthDTO(nickname: 'ok');

        $validator->validate($dto);
        $validator->validate($dto);
        $this->addToAssertionCount(1);
    }
}

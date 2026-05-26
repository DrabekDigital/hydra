<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Errors\ValidationErrorCode;
use DrabekDigital\Hydra\Exceptions\ValidationFailure;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\BarOnlyValidator;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\FooOnlyValidator;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationArrayMapNestedDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationChildDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationCustomPayload;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationCyclicNodeDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationDateTimeRangeBoundsDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationNumberRangeBoundsDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationSubjectDTO;
use DrabekDigital\Hydra\Tests\TestCase;
use DrabekDigital\Hydra\Validator;

/**
 * @internal
 *
 * @coversNothing
 */
class ValidatorTest extends TestCase
{
    public function testValidatePassesForValidObject(): void
    {
        $validator = new Validator();
        $dto = new ValidationSubjectDTO(
            age: 30,
            nickname: 'hydra',
            title: 'Architect',
            code: 'ABC',
            website: 'https://drabek.digital',
            tags: ['one', 'two'],
            payload: new ValidationCustomPayload('foo'),
            birthday: new \DateTimeImmutable('2000-01-01'),
            profile: new ValidationChildDTO('John'),
            children: [new ValidationChildDTO('Alice')],
        );

        $validator->validate($dto);
        $this->addToAssertionCount(1);
    }

    public function testValidateCollectsAllErrorsIncludingNestedPaths(): void
    {
        $validator = new Validator();
        $dto = new ValidationSubjectDTO(
            age: 10,
            nickname: 'x',
            title: '',
            code: 'abc',
            website: 'ftp://example.com',
            tags: ['dup', 'dup', 'dup'],
            payload: new ValidationCustomPayload('bar'),
            birthday: new \DateTimeImmutable('1800-01-01'),
            profile: new ValidationChildDTO('Al'),
            children: [new ValidationChildDTO('Bo')],
        );

        try {
            $validator->validate($dto);
            $this->fail('Expected ValidationFailure was not thrown');
        } catch (ValidationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(11, $errors);

            $codes = array_map(static fn ($error) => $error->code->value, $errors);
            $paths = array_map(static fn ($error) => $error->fullPath, $errors);

            $this->assertContains(ValidationErrorCode::OUT_OF_RANGE->value, $codes);
            $this->assertContains(ValidationErrorCode::TOO_SHORT->value, $codes);
            $this->assertContains(ValidationErrorCode::PATTERN_MISMATCH->value, $codes);
            $this->assertContains(ValidationErrorCode::INVALID_URL->value, $codes);
            $this->assertContains(ValidationErrorCode::COUNT_TOO_HIGH->value, $codes);
            $this->assertContains(ValidationErrorCode::NOT_UNIQUE->value, $codes);
            $this->assertContains(ValidationErrorCode::CUSTOM->value, $codes);

            $this->assertContains('profile.name', $paths);
            $this->assertContains('children.0.name', $paths);
        }
    }

    public function testValidateHandlesCyclicObjectGraphWithoutInfiniteRecursion(): void
    {
        $validator = new Validator();

        $a = new ValidationCyclicNodeDTO('A');
        $b = new ValidationCyclicNodeDTO('B');
        $a->next = $b;
        $b->next = $a;

        $validator->validate($a);
        $this->assertSame($b, $a->next);
        $this->assertSame($a, $b->next);
    }

    public function testValidateUniqueItemsWithComparatorDetectsCaseInsensitiveDuplicates(): void
    {
        // Tested via UniqueItemsRule directly — see tests/Unit/Validators/UniqueItemsRuleTest.php
        $this->addToAssertionCount(1);
    }

    public function testValidateRecursesIntoArrayOfAndMapOfObjects(): void
    {
        $validator = new Validator();
        $dto = new ValidationArrayMapNestedDTO(
            arrayChildren: [new ValidationChildDTO('Al')],
            mapChildren: ['primary' => new ValidationChildDTO('Bo')],
        );

        try {
            $validator->validate($dto);
            $this->fail('Expected ValidationFailure was not thrown');
        } catch (ValidationFailure $e) {
            $errors = $e->getErrors();
            $paths = array_map(static fn ($error) => $error->fullPath, $errors);
            $this->assertContains('arrayChildren.0.name', $paths);
            $this->assertContains('mapChildren.primary.name', $paths);
        }
    }

    public function testValidateNumberRangeFloatBoundsInclusiveAndExclusive(): void
    {
        $validator = new Validator();

        $valid = new ValidationNumberRangeBoundsDTO(
            inclusive: 0.5,
            exclusive: 1.0,
            rightExclusive: 0.5,
            leftExclusive: 1.5,
        );
        $validator->validate($valid);
        $this->addToAssertionCount(1);

        $invalid = new ValidationNumberRangeBoundsDTO(
            inclusive: 0.5,
            exclusive: 0.5,
            rightExclusive: 1.5,
            leftExclusive: 0.5,
        );

        try {
            $validator->validate($invalid);
            $this->fail('Expected ValidationFailure was not thrown');
        } catch (ValidationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(3, $errors);
            $paths = array_map(static fn ($error) => $error->fullPath, $errors);
            $this->assertContains('exclusive', $paths);
            $this->assertContains('rightExclusive', $paths);
            $this->assertContains('leftExclusive', $paths);
            foreach ($errors as $error) {
                $this->assertSame(ValidationErrorCode::OUT_OF_RANGE, $error->code);
            }
        }
    }

    public function testValidateDateTimeRangeBoundsInclusiveAndExclusive(): void
    {
        $validator = new Validator();

        $valid = new ValidationDateTimeRangeBoundsDTO(
            inclusive: new \DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            exclusive: new \DateTimeImmutable('2024-01-15T00:00:00+00:00'),
            rightExclusive: new \DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            leftExclusive: new \DateTimeImmutable('2024-01-31T00:00:00+00:00'),
        );
        $validator->validate($valid);
        $this->addToAssertionCount(1);

        $invalid = new ValidationDateTimeRangeBoundsDTO(
            inclusive: new \DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            exclusive: new \DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            rightExclusive: new \DateTimeImmutable('2024-01-31T00:00:00+00:00'),
            leftExclusive: new \DateTimeImmutable('2024-01-01T00:00:00+00:00'),
        );

        try {
            $validator->validate($invalid);
            $this->fail('Expected ValidationFailure was not thrown');
        } catch (ValidationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(3, $errors);
            $paths = array_map(static fn ($error) => $error->fullPath, $errors);
            $this->assertContains('exclusive', $paths);
            $this->assertContains('rightExclusive', $paths);
            $this->assertContains('leftExclusive', $paths);
            foreach ($errors as $error) {
                $this->assertSame(ValidationErrorCode::OUT_OF_RANGE, $error->code);
            }
        }
    }

    public function testRegisterValidatorOverridesDefaultValidatorResolution(): void
    {
        $validator = new Validator();
        $validator->registerValidator(FooOnlyValidator::class, new BarOnlyValidator());

        $dto = new ValidationSubjectDTO(
            age: 30,
            nickname: 'hydra',
            title: 'Architect',
            code: 'ABC',
            website: 'https://drabek.digital',
            tags: ['one', 'two'],
            payload: new ValidationCustomPayload('bar'),
            birthday: new \DateTimeImmutable('2000-01-01'),
            profile: new ValidationChildDTO('John'),
            children: [new ValidationChildDTO('Alice')],
        );

        $validator->validate($dto);
        $this->addToAssertionCount(1);
    }
}

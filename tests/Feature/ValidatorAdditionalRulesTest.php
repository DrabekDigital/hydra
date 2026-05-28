<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Errors\ValidationErrorCode;
use DrabekDigital\Hydra\Exceptions\ValidationFailure;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Status;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationAdditionalRulesDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationEnumChoiceDTO;
use DrabekDigital\Hydra\Tests\TestCase;
use DrabekDigital\Hydra\Validator;

/**
 * @internal
 *
 * @coversNothing
 */
class ValidatorAdditionalRulesTest extends TestCase
{
    public function testValidatePassesForAdditionalRulesWhenValuesAreValid(): void
    {
        $validator = new Validator();
        $dto = new ValidationAdditionalRulesDTO(
            email: 'john@example.com',
            requiredValue: 'present',
            mustBeNull: null,
            uuid: '550e8400-e29b-41d4-a716-446655440000',
            ip: '192.168.0.1',
            hostname: 'api.example.com',
            status: 'published',
        );

        $validator->validate($dto);
        $this->addToAssertionCount(1);
    }

    public function testValidateCollectsErrorsForAllAdditionalRules(): void
    {
        $validator = new Validator();
        $dto = new ValidationAdditionalRulesDTO(
            email: 'not-an-email',
            requiredValue: null,
            mustBeNull: 'not-null',
            uuid: 'not-a-uuid',
            ip: '999.999.999.999',
            hostname: '-invalid-hostname',
            status: 'invalid-status',
        );

        try {
            $validator->validate($dto);
            $this->fail('Expected ValidationFailure was not thrown');
        } catch (ValidationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(7, $errors);

            $codes = array_map(static fn ($error) => $error->code, $errors);
            $paths = array_map(static fn ($error) => $error->fullPath, $errors);

            $this->assertContains(ValidationErrorCode::INVALID_EMAIL, $codes);
            $this->assertContains(ValidationErrorCode::NOT_NULL, $codes);
            $this->assertContains(ValidationErrorCode::MUST_BE_NULL, $codes);
            $this->assertContains(ValidationErrorCode::INVALID_UUID, $codes);
            $this->assertContains(ValidationErrorCode::INVALID_IP, $codes);
            $this->assertContains(ValidationErrorCode::INVALID_HOSTNAME, $codes);
            $this->assertContains(ValidationErrorCode::INVALID_CHOICE, $codes);

            $this->assertContains('email', $paths);
            $this->assertContains('requiredValue', $paths);
            $this->assertContains('mustBeNull', $paths);
            $this->assertContains('uuid', $paths);
            $this->assertContains('ip', $paths);
            $this->assertContains('hostname', $paths);
            $this->assertContains('status', $paths);
        }
    }

    public function testValidateChoiceSupportsEnumValueSubsetValidation(): void
    {
        $validator = new Validator();

        $validator->validate(new ValidationEnumChoiceDTO(Status::Draft));
        $this->addToAssertionCount(1);

        try {
            $validator->validate(new ValidationEnumChoiceDTO(Status::Archived));
            $this->fail('Expected ValidationFailure was not thrown');
        } catch (ValidationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertSame(ValidationErrorCode::INVALID_CHOICE, $errors[0]->code);
            $this->assertSame('status', $errors[0]->fullPath);
        }
    }
}

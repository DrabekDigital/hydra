<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Errors\ValidationErrorCode;
use DrabekDigital\Hydra\Exceptions\ValidationFailure;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationConstraintAttributeDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationCustomPayload;
use DrabekDigital\Hydra\Tests\TestCase;
use DrabekDigital\Hydra\Validator;

/**
 * @internal
 *
 * @coversNothing
 */
class ValidatorConstraintAttributeTest extends TestCase
{
    public function testValidationConstraintAttributePassesWithoutCustomValidatorAttribute(): void
    {
        $validator = new Validator();
        $dto = new ValidationConstraintAttributeDTO(new ValidationCustomPayload('foo'));

        $validator->validate($dto);
        $this->addToAssertionCount(1);
    }

    public function testValidationConstraintAttributeFailsWhenValueDoesNotMatch(): void
    {
        $validator = new Validator();
        $dto = new ValidationConstraintAttributeDTO(new ValidationCustomPayload('bar'));

        try {
            $validator->validate($dto);
            $this->fail('Expected ValidationFailure was not thrown');
        } catch (ValidationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertSame(ValidationErrorCode::CUSTOM, $errors[0]->code);
            $this->assertSame('payload', $errors[0]->fullPath);
        }
    }
}

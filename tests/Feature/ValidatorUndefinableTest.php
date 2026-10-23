<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;
use DrabekDigital\Hydra\Exceptions\ValidationFailure;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ValidatorNonPublicPromotedDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ValidatorUndefinableChildDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ValidatorUndefinableDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ValidatorUndefinableNestedDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ValidatorUninitializedPublicPropertyDTO;
use DrabekDigital\Hydra\Tests\TestCase;
use DrabekDigital\Hydra\Validator;

/**
 * Undefined (uninitialized) properties are not validated: undefined is not the same as null,
 * so neither value rules nor `NotNull` apply and the validator does not recurse into them.
 *
 * @internal
 *
 * @coversNothing
 */
class ValidatorUndefinableTest extends TestCase
{
    public function testUndefinedFieldWithoutValidationAttributesPasses(): void
    {
        $dto = $this->hydrator->hydrate(['name' => 'Hydra'], ValidatorUndefinableDTO::class);

        new Validator()->validate($dto);
        $this->assertFalse(new \ReflectionProperty($dto, 'plain')->isInitialized($dto));
    }

    public function testUndefinedFieldsIgnoreValidationRulesIncludingNotNull(): void
    {
        $dto = $this->hydrator->hydrate(['name' => 'Hydra'], ValidatorUndefinableDTO::class);

        new Validator()->validate($dto);
        $this->assertFalse(new \ReflectionProperty($dto, 'nickname')->isInitialized($dto));
        $this->assertFalse(new \ReflectionProperty($dto, 'code')->isInitialized($dto));
    }

    public function testDefinedUndefinableFieldsAreValidated(): void
    {
        $dto = $this->hydrator->hydrate(
            ['name' => 'Hydra', 'plain' => null, 'nickname' => '', 'code' => null],
            ValidatorUndefinableDTO::class,
        );

        $errors = $this->collectErrors($dto);

        $this->assertSame(
            [
                ['nickname', ValidationErrorCode::TOO_SHORT],
                ['nickname', ValidationErrorCode::TOO_SHORT],
                ['code', ValidationErrorCode::NOT_NULL],
            ],
            array_map(static fn (ValidationError $error): array => [$error->fullPath, $error->code], $errors),
        );
    }

    public function testDefinedUndefinableFieldsWithValidValuesPass(): void
    {
        $dto = $this->hydrator->hydrate(
            ['name' => 'Hydra', 'plain' => 'anything', 'nickname' => 'hydra', 'code' => 'ABC'],
            ValidatorUndefinableDTO::class,
        );

        new Validator()->validate($dto);
        $this->addToAssertionCount(1);
    }

    public function testDefinedPropertiesAreStillValidatedNextToUndefinedOnes(): void
    {
        $dto = $this->hydrator->hydrate(['name' => 'Hy', 'nickname' => 'toolongnickname'], ValidatorUndefinableDTO::class);

        $errors = $this->collectErrors($dto);

        $this->assertSame(
            [
                ['name', ValidationErrorCode::TOO_SHORT],
                ['nickname', ValidationErrorCode::TOO_LONG],
            ],
            array_map(static fn (ValidationError $error): array => [$error->fullPath, $error->code], $errors),
        );
    }

    public function testUndefinedNestedDtoAndListAreNotTraversed(): void
    {
        $dto = $this->hydrator->hydrate(['name' => 'Hydra'], ValidatorUndefinableNestedDTO::class);

        new Validator()->validate($dto);
        $this->assertFalse(new \ReflectionProperty($dto, 'child')->isInitialized($dto));
        $this->assertFalse(new \ReflectionProperty($dto, 'children')->isInitialized($dto));
    }

    public function testDefinedNestedDtoAndListAreTraversed(): void
    {
        $dto = $this->hydrator->hydrate(
            ['name' => 'Hydra', 'child' => ['name' => 'Al'], 'children' => [['name' => 'Alice'], ['name' => 'Bo']]],
            ValidatorUndefinableNestedDTO::class,
        );

        $errors = $this->collectErrors($dto);

        $this->assertSame(
            [
                ['child.name', ValidationErrorCode::TOO_SHORT],
                ['children.1.name', ValidationErrorCode::TOO_SHORT],
            ],
            array_map(static fn (ValidationError $error): array => [$error->fullPath, $error->code], $errors),
        );
    }

    public function testDefinedEmptyListIsValidated(): void
    {
        $dto = $this->hydrator->hydrate(['name' => 'Hydra', 'children' => []], ValidatorUndefinableNestedDTO::class);

        $errors = $this->collectErrors($dto);

        $this->assertCount(1, $errors);
        $this->assertSame('children', $errors[0]->fullPath);
        $this->assertSame(ValidationErrorCode::COUNT_TOO_LOW, $errors[0]->code);
    }

    public function testUninitializedPublicPropertiesWithoutConstructorAreSkipped(): void
    {
        new Validator()->validate(new ValidatorUninitializedPublicPropertyDTO());
        $this->addToAssertionCount(1);
    }

    public function testInitializedPublicPropertiesWithoutConstructorAreValidated(): void
    {
        $dto = new ValidatorUninitializedPublicPropertyDTO();
        $dto->title = 'Hy';
        $dto->child = new ValidatorUndefinableChildDTO('Al');

        $errors = $this->collectErrors($dto);

        $this->assertSame(
            [
                ['title', ValidationErrorCode::TOO_SHORT],
                ['child.name', ValidationErrorCode::TOO_SHORT],
            ],
            array_map(static fn (ValidationError $error): array => [$error->fullPath, $error->code], $errors),
        );
    }

    public function testNonPublicPromotedPropertiesAreValidated(): void
    {
        $dto = $this->hydrator->hydrate(
            ['name' => 'Hy', 'child' => ['name' => 'Al'], 'alias' => 'x'],
            ValidatorNonPublicPromotedDTO::class,
        );

        $errors = $this->collectErrors($dto);

        $this->assertSame(
            [
                ['name', ValidationErrorCode::TOO_SHORT],
                ['child.name', ValidationErrorCode::TOO_SHORT],
                ['alias', ValidationErrorCode::TOO_SHORT],
            ],
            array_map(static fn (ValidationError $error): array => [$error->fullPath, $error->code], $errors),
        );
    }

    public function testUndefinedNonPublicPromotedPropertyIsSkipped(): void
    {
        $dto = $this->hydrator->hydrate(
            ['name' => 'Hydra', 'child' => ['name' => 'Alice']],
            ValidatorNonPublicPromotedDTO::class,
        );

        new Validator()->validate($dto);
        $this->assertFalse(new \ReflectionProperty($dto, 'alias')->isInitialized($dto));
    }

    /**
     * @return list<ValidationError>
     */
    private function collectErrors(object $dto): array
    {
        try {
            new Validator()->validate($dto);
        } catch (ValidationFailure $e) {
            return array_values($e->getErrors());
        }

        $this->fail('Expected ValidationFailure was not thrown');
    }
}

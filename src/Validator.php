<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra;

use DrabekDigital\Hydra\Attributes\ArrayOf;
use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Attributes\MapOf;
use DrabekDigital\Hydra\Attributes\Validation\Count;
use DrabekDigital\Hydra\Attributes\Validation\CustomValidator;
use DrabekDigital\Hydra\Attributes\Validation\DateTimeRange;
use DrabekDigital\Hydra\Attributes\Validation\LengthRange;
use DrabekDigital\Hydra\Attributes\Validation\NonEmptyString;
use DrabekDigital\Hydra\Attributes\Validation\NumberRange;
use DrabekDigital\Hydra\Attributes\Validation\StringMatches;
use DrabekDigital\Hydra\Attributes\Validation\UniqueItems;
use DrabekDigital\Hydra\Attributes\Validation\URL;
use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Exceptions\ValidationFailure;
use DrabekDigital\Hydra\Metadata\ClassAnalyzer;
use DrabekDigital\Hydra\Validators\Rules\CountRule;
use DrabekDigital\Hydra\Validators\Rules\DateTimeRangeRule;
use DrabekDigital\Hydra\Validators\Rules\LengthRangeRule;
use DrabekDigital\Hydra\Validators\Rules\NonEmptyStringRule;
use DrabekDigital\Hydra\Validators\Rules\NumberRangeRule;
use DrabekDigital\Hydra\Validators\Rules\StringMatchesRule;
use DrabekDigital\Hydra\Validators\Rules\UniqueItemsRule;
use DrabekDigital\Hydra\Validators\Rules\UrlRule;
use DrabekDigital\Hydra\Validators\Validator as ValidatorInterface;

class Validator
{
    /** @var array<class-string<ValidatorInterface>, ValidatorInterface> */
    private array $customValidators = [];

    public function __construct(
        private readonly ?ClassAnalyzer $analyzer = null,
    ) {}

    /**
     * @param class-string<ValidatorInterface> $validatorClass
     */
    public function registerValidator(string $validatorClass, ValidatorInterface $validator): self
    {
        $this->customValidators[$validatorClass] = $validator;

        return $this;
    }

    /**
     * @throws ValidationFailure
     */
    public function validate(object $object): void
    {
        $errors = [];

        /** @var \SplObjectStorage<object, bool> $visited */
        $visited = new \SplObjectStorage();
        $this->validateObject($object, '', $errors, $visited);

        if ($errors !== []) {
            throw new ValidationFailure($errors);
        }
    }

    /**
     * @param array<ValidationError>          $errors
     * @param \SplObjectStorage<object, bool> $visited
     */
    private function validateObject(object $object, string $path, array &$errors, \SplObjectStorage $visited): void
    {
        if ($visited->offsetExists($object)) {
            return;
        }
        $visited->offsetSet($object, true);

        $analyzer = $this->analyzer ?? new ClassAnalyzer();
        $properties = $analyzer->analyze($object::class);

        foreach ($properties as $property) {
            if (!property_exists($object, $property->name)) {
                continue;
            }

            $value = $object->{$property->name};
            $fullPath = $path === '' ? $property->name : "{$path}.{$property->name}";

            $this->applyValidationAttributes($property->name, $value, $fullPath, $property->attributes, $errors);
            $this->validateNested($value, $property->attributes, $fullPath, $errors, $visited);
        }
    }

    /**
     * @param array<string, object>  $attributes
     * @param array<ValidationError> $errors
     */
    private function applyValidationAttributes(
        string $key,
        mixed $value,
        string $fullPath,
        array $attributes,
        array &$errors,
    ): void {
        $numberRange = $attributes[NumberRange::class] ?? null;
        if ($numberRange instanceof NumberRange && (is_int($value) || is_float($value))) {
            array_push($errors, ...new NumberRangeRule($numberRange)->validate($value, $key, $fullPath));
        }

        $lengthRange = $attributes[LengthRange::class] ?? null;
        if ($lengthRange instanceof LengthRange && is_string($value)) {
            array_push($errors, ...new LengthRangeRule($lengthRange)->validate($value, $key, $fullPath));
        }

        $nonEmpty = $attributes[NonEmptyString::class] ?? null;
        if ($nonEmpty instanceof NonEmptyString && is_string($value)) {
            array_push($errors, ...new NonEmptyStringRule()->validate($value, $key, $fullPath));
        }

        $stringMatches = $attributes[StringMatches::class] ?? null;
        if ($stringMatches instanceof StringMatches && is_string($value)) {
            array_push($errors, ...new StringMatchesRule($stringMatches)->validate($value, $key, $fullPath));
        }

        $url = $attributes[URL::class] ?? null;
        if ($url instanceof URL && is_string($value)) {
            array_push($errors, ...new UrlRule($url)->validate($value, $key, $fullPath));
        }

        $count = $attributes[Count::class] ?? null;
        if ($count instanceof Count && is_array($value)) {
            array_push($errors, ...new CountRule($count)->validate($value, $key, $fullPath));
        }

        $dateTimeRange = $attributes[DateTimeRange::class] ?? null;
        if ($dateTimeRange instanceof DateTimeRange && $value instanceof \DateTimeInterface) {
            array_push($errors, ...new DateTimeRangeRule($dateTimeRange)->validate($value, $key, $fullPath));
        }

        $uniqueItems = $attributes[UniqueItems::class] ?? null;
        if ($uniqueItems instanceof UniqueItems && is_array($value)) {
            array_push($errors, ...new UniqueItemsRule($uniqueItems)->validate($value, $key, $fullPath));
        }

        $customValidator = $attributes[CustomValidator::class] ?? null;
        if ($customValidator instanceof CustomValidator) {
            $validator = $this->getCustomValidator($customValidator->validator);
            $error = $validator->validate($value, $key, $fullPath);
            if ($error !== null) {
                $errors[] = $error;
            }
        }
    }

    /**
     * @param array<string, object>           $attributes
     * @param array<ValidationError>          $errors
     * @param \SplObjectStorage<object, bool> $visited
     */
    private function validateNested(mixed $value, array $attributes, string $fullPath, array &$errors, \SplObjectStorage $visited): void
    {
        if (is_object($value)) {
            $this->validateObject($value, $fullPath, $errors, $visited);

            return;
        }

        if (!is_array($value)) {
            return;
        }

        $listOf = $attributes[ListOf::class] ?? null;
        if ($listOf instanceof ListOf) {
            foreach ($value as $idx => $item) {
                if (is_object($item)) {
                    $this->validateObject($item, "{$fullPath}.{$idx}", $errors, $visited);
                }
            }
        }

        $arrayOf = $attributes[ArrayOf::class] ?? null;
        if ($arrayOf instanceof ArrayOf) {
            foreach ($value as $idx => $item) {
                if (is_object($item)) {
                    $this->validateObject($item, "{$fullPath}.{$idx}", $errors, $visited);
                }
            }
        }

        $mapOf = $attributes[MapOf::class] ?? null;
        if ($mapOf instanceof MapOf) {
            foreach ($value as $mapKey => $item) {
                if (is_object($item)) {
                    $this->validateObject($item, "{$fullPath}.{$mapKey}", $errors, $visited);
                }
            }
        }
    }

    /**
     * @param class-string<ValidatorInterface> $validatorClass
     */
    private function getCustomValidator(string $validatorClass): ValidatorInterface
    {
        if (isset($this->customValidators[$validatorClass])) {
            return $this->customValidators[$validatorClass];
        }

        $validator = new $validatorClass();
        $this->customValidators[$validatorClass] = $validator;

        return $validator;
    }
}

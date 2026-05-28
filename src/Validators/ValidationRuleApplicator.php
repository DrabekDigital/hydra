<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Validators;

use DrabekDigital\Hydra\Attributes\Validation\Choice;
use DrabekDigital\Hydra\Attributes\Validation\Count;
use DrabekDigital\Hydra\Attributes\Validation\DateTimeRange;
use DrabekDigital\Hydra\Attributes\Validation\Email;
use DrabekDigital\Hydra\Attributes\Validation\Hostname;
use DrabekDigital\Hydra\Attributes\Validation\Ip;
use DrabekDigital\Hydra\Attributes\Validation\IsNull;
use DrabekDigital\Hydra\Attributes\Validation\LengthRange;
use DrabekDigital\Hydra\Attributes\Validation\NonEmptyString;
use DrabekDigital\Hydra\Attributes\Validation\NotNull;
use DrabekDigital\Hydra\Attributes\Validation\NumberRange;
use DrabekDigital\Hydra\Attributes\Validation\StringMatches;
use DrabekDigital\Hydra\Attributes\Validation\TypedValidationAttribute;
use DrabekDigital\Hydra\Attributes\Validation\UniqueItems;
use DrabekDigital\Hydra\Attributes\Validation\URL;
use DrabekDigital\Hydra\Attributes\Validation\Uuid;
use DrabekDigital\Hydra\Attributes\Validation\ValidationConstraint;
use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Validators\Rules\ChoiceRule;
use DrabekDigital\Hydra\Validators\Rules\CountRule;
use DrabekDigital\Hydra\Validators\Rules\DateTimeRangeRule;
use DrabekDigital\Hydra\Validators\Rules\EmailRule;
use DrabekDigital\Hydra\Validators\Rules\HostnameRule;
use DrabekDigital\Hydra\Validators\Rules\IpRule;
use DrabekDigital\Hydra\Validators\Rules\LengthRangeRule;
use DrabekDigital\Hydra\Validators\Rules\NonEmptyStringRule;
use DrabekDigital\Hydra\Validators\Rules\NotNullRule;
use DrabekDigital\Hydra\Validators\Rules\NullRule;
use DrabekDigital\Hydra\Validators\Rules\NumberRangeRule;
use DrabekDigital\Hydra\Validators\Rules\StringMatchesRule;
use DrabekDigital\Hydra\Validators\Rules\UniqueItemsRule;
use DrabekDigital\Hydra\Validators\Rules\UrlRule;
use DrabekDigital\Hydra\Validators\Rules\UuidRule;
use DrabekDigital\Hydra\Validators\Validator as ValidatorInterface;

final class ValidationRuleApplicator
{
    /** @var array<class-string, AttributeValidator|ValidatorInterface> */
    private array $customValidators;

    /**
     * @param array<class-string, AttributeValidator|ValidatorInterface> $customValidators
     */
    public function __construct(array $customValidators)
    {
        $this->customValidators = $customValidators;
    }

    /**
     * @param array<string, object> $attributes
     *
     * @return list<ValidationError>
     */
    public function apply(string $key, mixed $value, string $fullPath, array $attributes): array
    {
        $errors = [];

        $notNull = $attributes[NotNull::class] ?? null;
        if ($notNull instanceof NotNull) {
            array_push($errors, ...$this->applyTyped($notNull, $value, $key, $fullPath, fn () => new NotNullRule($notNull)->validate($value, $key, $fullPath)));
        }

        $isNull = $attributes[IsNull::class] ?? null;
        if ($isNull instanceof IsNull) {
            array_push($errors, ...$this->applyTyped($isNull, $value, $key, $fullPath, fn () => new NullRule($isNull)->validate($value, $key, $fullPath)));
        }

        $numberRange = $attributes[NumberRange::class] ?? null;
        if ($numberRange instanceof NumberRange) {
            array_push($errors, ...$this->applyTyped($numberRange, $value, $key, $fullPath, fn () => $this->runIfNumber($numberRange, $value, $key, $fullPath, static fn (float|int $v) => new NumberRangeRule($numberRange)->validate($v, $key, $fullPath))));
        }

        $lengthRange = $attributes[LengthRange::class] ?? null;
        if ($lengthRange instanceof LengthRange) {
            array_push($errors, ...$this->applyTyped($lengthRange, $value, $key, $fullPath, fn () => $this->runIfString($lengthRange, $value, $key, $fullPath, static fn (string $v) => new LengthRangeRule($lengthRange)->validate($v, $key, $fullPath))));
        }

        $nonEmpty = $attributes[NonEmptyString::class] ?? null;
        if ($nonEmpty instanceof NonEmptyString) {
            array_push($errors, ...$this->applyTyped($nonEmpty, $value, $key, $fullPath, fn () => $this->runIfString($nonEmpty, $value, $key, $fullPath, static fn (string $v) => new NonEmptyStringRule()->validate($v, $key, $fullPath))));
        }

        $stringMatches = $attributes[StringMatches::class] ?? null;
        if ($stringMatches instanceof StringMatches) {
            array_push($errors, ...$this->applyTyped($stringMatches, $value, $key, $fullPath, fn () => $this->runIfString($stringMatches, $value, $key, $fullPath, static fn (string $v) => new StringMatchesRule($stringMatches)->validate($v, $key, $fullPath))));
        }

        $url = $attributes[URL::class] ?? null;
        if ($url instanceof URL) {
            array_push($errors, ...$this->applyTyped($url, $value, $key, $fullPath, fn () => $this->runIfString($url, $value, $key, $fullPath, static fn (string $v) => new UrlRule($url)->validate($v, $key, $fullPath))));
        }

        $email = $attributes[Email::class] ?? null;
        if ($email instanceof Email) {
            array_push($errors, ...$this->applyTyped($email, $value, $key, $fullPath, fn () => $this->runIfString($email, $value, $key, $fullPath, static fn (string $v) => new EmailRule($email)->validate($v, $key, $fullPath))));
        }

        $uuid = $attributes[Uuid::class] ?? null;
        if ($uuid instanceof Uuid) {
            array_push($errors, ...$this->applyTyped($uuid, $value, $key, $fullPath, fn () => $this->runIfString($uuid, $value, $key, $fullPath, static fn (string $v) => new UuidRule($uuid)->validate($v, $key, $fullPath))));
        }

        $ip = $attributes[Ip::class] ?? null;
        if ($ip instanceof Ip) {
            array_push($errors, ...$this->applyTyped($ip, $value, $key, $fullPath, fn () => $this->runIfString($ip, $value, $key, $fullPath, static fn (string $v) => new IpRule($ip)->validate($v, $key, $fullPath))));
        }

        $hostname = $attributes[Hostname::class] ?? null;
        if ($hostname instanceof Hostname) {
            array_push($errors, ...$this->applyTyped($hostname, $value, $key, $fullPath, fn () => $this->runIfString($hostname, $value, $key, $fullPath, static fn (string $v) => new HostnameRule($hostname)->validate($v, $key, $fullPath))));
        }

        $choice = $attributes[Choice::class] ?? null;
        if ($choice instanceof Choice) {
            array_push($errors, ...$this->applyTyped($choice, $value, $key, $fullPath, fn () => new ChoiceRule($choice)->validate($value, $key, $fullPath)));
        }

        $count = $attributes[Count::class] ?? null;
        if ($count instanceof Count) {
            array_push($errors, ...$this->applyTyped($count, $value, $key, $fullPath, fn () => $this->runIfArray($count, $value, $key, $fullPath, static fn (array $v) => new CountRule($count)->validate($v, $key, $fullPath))));
        }

        $dateTimeRange = $attributes[DateTimeRange::class] ?? null;
        if ($dateTimeRange instanceof DateTimeRange) {
            array_push($errors, ...$this->applyTyped($dateTimeRange, $value, $key, $fullPath, fn () => $this->runIfDateTime($dateTimeRange, $value, $key, $fullPath, static fn (\DateTimeInterface $v) => new DateTimeRangeRule($dateTimeRange)->validate($v, $key, $fullPath))));
        }

        $uniqueItems = $attributes[UniqueItems::class] ?? null;
        if ($uniqueItems instanceof UniqueItems) {
            array_push($errors, ...$this->applyTyped($uniqueItems, $value, $key, $fullPath, fn () => $this->runIfArray($uniqueItems, $value, $key, $fullPath, static fn (array $v) => new UniqueItemsRule($uniqueItems)->validate($v, $key, $fullPath))));
        }

        foreach ($attributes as $attribute) {
            if (!$attribute instanceof ValidationConstraint) {
                continue;
            }

            $error = $this->validateConstraint($attribute, $value, $key, $fullPath);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * @param callable(): list<ValidationError> $run
     *
     * @return list<ValidationError>
     */
    private function applyTyped(
        TypedValidationAttribute $attribute,
        mixed $value,
        string $key,
        string $fullPath,
        callable $run,
    ): array {
        return match (ValidationRuleExecution::resolve($attribute, $value)) {
            ValidationRuleExecutionResult::Skip => [],
            ValidationRuleExecutionResult::Apply => ValidationRuleExecution::isApplicable($attribute, $value)
                ? $run()
                : [ValidationRuleExecution::typeMismatchError($key, $fullPath, $attribute)],
            ValidationRuleExecutionResult::TypeMismatch => [
                ValidationRuleExecution::typeMismatchError($key, $fullPath, $attribute),
            ],
            ValidationRuleExecutionResult::NullFailure => [
                ValidationRuleExecution::nullFailureError($attribute, $key, $fullPath),
            ],
        };
    }

    /**
     * @param callable(string): list<ValidationError> $validate
     *
     * @return list<ValidationError>
     */
    private function runIfString(
        TypedValidationAttribute $attribute,
        mixed $value,
        string $key,
        string $fullPath,
        callable $validate,
    ): array {
        if (!is_string($value)) {
            return [ValidationRuleExecution::typeMismatchError($key, $fullPath, $attribute)];
        }

        return $validate($value);
    }

    /**
     * @param callable(float|int): list<ValidationError> $validate
     *
     * @return list<ValidationError>
     */
    private function runIfNumber(
        TypedValidationAttribute $attribute,
        mixed $value,
        string $key,
        string $fullPath,
        callable $validate,
    ): array {
        if (!is_int($value) && !is_float($value)) {
            return [ValidationRuleExecution::typeMismatchError($key, $fullPath, $attribute)];
        }

        return $validate($value);
    }

    /**
     * @param callable(array<mixed>): list<ValidationError> $validate
     *
     * @return list<ValidationError>
     */
    private function runIfArray(
        TypedValidationAttribute $attribute,
        mixed $value,
        string $key,
        string $fullPath,
        callable $validate,
    ): array {
        if (!is_array($value)) {
            return [ValidationRuleExecution::typeMismatchError($key, $fullPath, $attribute)];
        }

        return $validate($value);
    }

    /**
     * @param callable(\DateTimeInterface): list<ValidationError> $validate
     *
     * @return list<ValidationError>
     */
    private function runIfDateTime(
        TypedValidationAttribute $attribute,
        mixed $value,
        string $key,
        string $fullPath,
        callable $validate,
    ): array {
        if (!$value instanceof \DateTimeInterface) {
            return [ValidationRuleExecution::typeMismatchError($key, $fullPath, $attribute)];
        }

        return $validate($value);
    }

    private function validateConstraint(
        ValidationConstraint $constraint,
        mixed $value,
        string $key,
        string $fullPath,
    ): ?ValidationError {
        $validator = $this->resolveCustomValidator($constraint->validatorClass());

        if ($validator instanceof AttributeValidator) {
            return $validator->validateWithConstraint($value, $key, $fullPath, $constraint);
        }

        return $validator->validate($value, $key, $fullPath);
    }

    /**
     * @param class-string<AttributeValidator|ValidatorInterface> $validatorClass
     */
    private function resolveCustomValidator(string $validatorClass): AttributeValidator|ValidatorInterface
    {
        if (isset($this->customValidators[$validatorClass])) {
            return $this->customValidators[$validatorClass];
        }

        $validator = new $validatorClass();
        $this->customValidators[$validatorClass] = $validator;

        return $validator;
    }
}

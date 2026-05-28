<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Validators;

use DrabekDigital\Hydra\Attributes\Validation\ValidationConstraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class RequiresFooPayload implements ValidationConstraint
{
    public function __construct(
        public readonly string $expectedValue = 'foo',
    ) {}

    public function validatorClass(): string
    {
        return RequiresFooPayloadValidator::class;
    }
}

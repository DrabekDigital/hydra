<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes\Validation;

enum NullValidationBehavior
{
    /** Do not run the rule when the value is null. */
    case SkipWhenNull;

    /** Treat null as a validation failure for this rule. */
    case FailWhenNull;

    /**
     * Always run the rule regardless of null (e.g. IsNull, NotNull).
     *
     * @internal
     */
    case ValidateAlways;
}

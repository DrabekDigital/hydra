<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Validators;

enum ValidationRuleExecutionResult
{
    case Apply;
    case Skip;
    case TypeMismatch;
    case NullFailure;
}

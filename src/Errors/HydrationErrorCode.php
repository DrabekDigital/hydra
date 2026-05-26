<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Errors;

enum HydrationErrorCode: string
{
    case MISSING_KEY = 'missing_key';
    case TYPE_MISMATCH = 'type_mismatch';
    case INVALID_VALUE = 'invalid_value';
    case UNEXPECTED_NULL = 'unexpected_null';
    case UNEXPECTED_KEY = 'unexpected_key';
}

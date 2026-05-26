<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Errors;

enum ValidationErrorCode: string
{
    case OUT_OF_RANGE = 'out_of_range';
    case TOO_SHORT = 'too_short';
    case TOO_LONG = 'too_long';
    case PATTERN_MISMATCH = 'pattern_mismatch';
    case INVALID_URL = 'invalid_url';
    case COUNT_TOO_LOW = 'count_too_low';
    case COUNT_TOO_HIGH = 'count_too_high';
    case NOT_UNIQUE = 'not_unique';
    case CUSTOM = 'custom';
}

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
    case INVALID_EMAIL = 'invalid_email';
    case NOT_NULL = 'not_null';
    case MUST_BE_NULL = 'must_be_null';
    case INVALID_UUID = 'invalid_uuid';
    case INVALID_IP = 'invalid_ip';
    case INVALID_HOSTNAME = 'invalid_hostname';
    case INVALID_CHOICE = 'invalid_choice';
    case COUNT_TOO_LOW = 'count_too_low';
    case COUNT_TOO_HIGH = 'count_too_high';
    case NOT_UNIQUE = 'not_unique';
    case UNIQUE_ITEMS_COMPARATOR_REQUIRED = 'unique_items_comparator_required';
    case RULE_NOT_APPLICABLE = 'rule_not_applicable';
    case NOT_INITIALIZED = 'not_initialized';
    case CUSTOM = 'custom';
}

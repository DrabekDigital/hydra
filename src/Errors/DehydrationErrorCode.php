<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Errors;

enum DehydrationErrorCode: string
{
    case TYPE_MISMATCH = 'type_mismatch';
    case INVALID_VALUE = 'invalid_value';
    case UNINITIALIZED_PROPERTY = 'uninitialized_property';
}

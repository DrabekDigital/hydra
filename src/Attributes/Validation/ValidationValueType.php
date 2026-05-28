<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes\Validation;

enum ValidationValueType
{
    case String;
    case Int;
    case Float;
    case Array;
    case DateTimeInterface;
    case Enum;
    case Mixed;
}

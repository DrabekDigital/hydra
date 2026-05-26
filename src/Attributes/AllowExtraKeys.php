<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes;

/**
 * Allow extra keys in input data that don't map to properties
 * By default, extra keys will cause an error.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AllowExtraKeys {}

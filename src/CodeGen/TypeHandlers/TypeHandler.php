<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\CodeGen\TypeHandlers;

use DrabekDigital\Hydra\CodeGen\GenerationContext;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use PhpParser\Node\Stmt;

/**
 * Interface for type-specific code generation handlers.
 */
interface TypeHandler
{
    /**
     * Priority for handler selection (higher = checked first)
     * Use higher numbers for more specific handlers.
     */
    public function priority(): int;

    /**
     * Check if this handler can process the given property.
     */
    public function supports(PropertyMetadata $property, GenerationContext $context): bool;

    /**
     * Generate hydration code for this property.
     *
     * @return Stmt[]
     */
    public function generateHydration(PropertyMetadata $property, GenerationContext $context): array;

    /**
     * Generate dehydration code for this property.
     *
     * @return Stmt[]
     */
    public function generateDehydration(PropertyMetadata $property, GenerationContext $context): array;
}

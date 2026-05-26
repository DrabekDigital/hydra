<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\CodeGen\TypeHandlers;

use DrabekDigital\Hydra\CodeGen\GenerationContext;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;

/**
 * Registry that manages type handlers and selects the appropriate one.
 */
final class TypeHandlerRegistry
{
    /** @var TypeHandler[] */
    private array $handlers = [];

    private bool $sorted = false;

    public function register(TypeHandler $handler): self
    {
        $this->handlers[] = $handler;
        $this->sorted = false;

        return $this;
    }

    /**
     * Get the appropriate handler for a property.
     *
     * @throws \RuntimeException if no handler supports the property
     */
    public function getHandler(PropertyMetadata $property, GenerationContext $context): TypeHandler
    {
        $this->ensureSorted();

        foreach ($this->handlers as $handler) {
            if ($handler->supports($property, $context)) {
                return $handler;
            }
        }

        throw new \RuntimeException(
            "No type handler found for property '{$property->name}' with type(s): ".implode('|', $property->types)
        );
    }

    /**
     * Check if any handler supports the property.
     */
    public function hasHandler(PropertyMetadata $property, GenerationContext $context): bool
    {
        $this->ensureSorted();

        foreach ($this->handlers as $handler) {
            if ($handler->supports($property, $context)) {
                return true;
            }
        }

        return false;
    }

    private function ensureSorted(): void
    {
        if (!$this->sorted) {
            usort($this->handlers, fn (TypeHandler $a, TypeHandler $b) => $b->priority() <=> $a->priority());
            $this->sorted = true;
        }
    }
}

# Coding Conventions for AI Agents

This document defines coding standards that AI agents should follow when working on this project.

## PHP Code Style

### General
- Use `declare(strict_types=1)` at the top of every PHP file
- Use PHP 8.4+ features where appropriate
- Use `readonly` properties for DTOs if possible
- Prefer constructor property promotion if possible

### Naming Conventions

| Element | Convention | Example |
|---------|------------|---------|
| Classes | PascalCase | `HydratorGenerator` |
| Methods | camelCase | `generateHydration()` |
| Properties | camelCase | `$nestedTypes` |
| Constants | UPPER_SNAKE | `CACHE_TYPE` |
| Interfaces | PascalCase + suffix | `CacheInterface` |
| Enums | PascalCase | `HydrationErrorCode` |

### Test Methods

Use **camelCase** for test method names.

### Namespace Structure

```
DrabekDigital\Hydra\
├── Attributes\           # PHP attributes for DTOs
│   └── Validation\       # Validation-specific attributes
├── Caching\              # Cache implementations
├── CodeGen\              # Code generation
│   ├── Ast\              # AST helpers
│   └── TypeHandlers\     # Type-specific handlers
├── Errors\               # Error classes and codes
├── Exceptions\           # Exception classes
├── Mappers\              # Custom mapper interface
├── Metadata\             # Class analysis
├── NamingStrategies\     # Naming strategy implementations
└── Validators\           # Validator interface
```

## Testing

- Use PHPUnit 12+
- Place unit tests in `tests/Unit/`
- Place feature/integration tests in `tests/Feature/`
- Place test fixtures (DTOs, Enums, Mappers) in `tests/Fixtures/` — **one class per file**
- Extend `TestCase` base class for assertion helpers

### Test Assertions

Prefer fluent assertion helpers from `TestCase`:

```php
// Hydration
$this->assertHydrates($data, DTO::class, fn($dto) => ...);

// Dehydration
$this->assertDehydrates($object, $expectedArray);

// Roundtrip
$this->assertRoundtrip($data, DTO::class);

// Error cases
$this->assertHydrationFails($data, DTO::class, 'error_code', 'key');
```

## Architectural Decisions (Important)

- **Attribute-driven contracts**: DTO shape, mapping and validation are defined by PHP attributes and type declarations; do not introduce external schema/config layers for core flow.
- **Generated runtime code**: hydration/dehydration are implemented via generated PHP classes (AST + pretty printer), not reflection-heavy runtime mapping on every call.
- **Cache-first execution**: generated hydrators/dehydrators are cached behind `Caching\CacheInterface`; prefer stable cache keys and invalidate through existing `clearCache()` APIs.
- **Structure-hash invalidation**: cache keys depend on class structure hashes from `Metadata\ClassAnalyzer` (types, defaults, attributes); preserve this mechanism when changing metadata logic.
- **Recursive object graph handling**: nested DTOs, `ListOf`/array mappings and discriminated arrays are processed recursively by registering nested hydrators/dehydrators.
- **Strict typing over coercion**: type coercion is intentionally avoided; keep explicit checks and fail with domain errors instead of silently casting.
- **Separated responsibilities**: `Hydrator`/`Dehydrator` perform transform + mapping, `Validator` performs validation and recursive traversal; avoid mixing these concerns.
- **Pluggable extensions**: custom mappers and custom validators are extensibility points and can be registered or lazily instantiated; keep extension APIs backwards compatible.

## Documentation

- Use PHPDoc for complex methods
- Document `@template`, `@param`, `@return` for generics
- Keep inline comments minimal - prefer self-documenting code


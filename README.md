# Hydra 🐉

This is a serialization & deserialization & validation library with minimum dependencies to allow you
easily guard the app's valuable asset - data integrity. All that in PHP using top-notch PHP 8.4 language features. 

The main principles of this approach are:

- Specification of serialization & deserialization & validation via PHP attributes in the DTOs
- First pass generates a DTOs hierarchy with hydration/dehydration logic in place through normal PHP code (`isset`, `array_key_exists`, `is_string`, `is_int`, ...) and caches generated runtime for subsequent runs.
- Validation is a separate runtime flow (`Validator`) and is not code-generated.
- Attributes should be specified above properties only when needed (in other words native typing should be used when possible)
- There should be sane defaults for most of the situations.
- Validation & dehydration & hydration are recursive.

Limitations:

- Circular references are not supported.
- Type coercion is prevented by design.

## Naming strategy

Bidirectional way how the fields are mapped during serialization & deserialization.

- Default (1:1 mapping)
- `UnderscoreCase`: use when in serialized format you have `underscore_case` while members are named in `camelCase`

Also there is possibility to rename a particular field (for legacy named fields) by using `Rename` attribute.
`Rename` is direction-aware: use `from` for hydration (input) and `to` for dehydration (output).

## Mappers

- Scalars
  - `bool`
  - `float`
  - `int`
  - `mixed`
  - `string`

Special types:

- `DateTimeInterface` and `DateTimeImmutable`
- `BackedEnum`
- Array and lists
  - `array` (of anything)
  - `List`of particular value type (with discriminator)
  - `Map` of particular key & value type (with discriminator)
- Shapes
  - `Object` of particular type (with discriminator)
- Custom mappers
  - `CustomMapper` of particular type (or your own attribute implementing `MapperAttribute` interface)

## Validators

- Integer & float
  - Range (min, max, inclusive, exclusive) - `NumberRange`
- Strings
  - `LengthRange` (min, max) with one particular alias `NonEmptyString`
  - Regex - `StringMatches`
  - URL
- Array
  - Count (min, max)
  - Unique items (including custom comparator)
- Date & time
  - `DateTimeRange` (min, max, inclusive, exclusive)
- Custom

---

## Usage

```php
<?php declare(strict_types=1);

use DrabekDigital\Hydra\Caching\FileCache;
use DrabekDigital\Hydra\Caching\Psr16CacheAdapter;
use DrabekDigital\Hydra\Dehydrator;
use DrabekDigital\Hydra\Hydrator;
use DrabekDigital\Hydra\Exceptions\HydrationFailure;
use DrabekDigital\Hydra\Exceptions\DehydrationFailure;
use DrabekDigital\Hydra\Exceptions\ValidationFailure;
use DrabekDigital\Hydra\Validator;
use Psr\SimpleCache\CacheInterface as Psr16CacheInterface;

// Option A: built-in file cache (use a dedicated/private directory, not a shared world-writable temp path)
$cache = new FileCache(sys_get_temp_dir());

// Option B: any PSR-16 cache implementation from your DI container/framework
/** @var Psr16CacheInterface $psr16 */
$psr16 = $container->get(Psr16CacheInterface::class);
$cache = new Psr16CacheAdapter($psr16);

// Hydrating from array to object
$hydrator = new Hydrator($cache);
try {
    $object = $hydrator->hydrate($input, MyDTO::class);
    $object = $hydrator->hydrateArray($input, discriminator: 'type', mapping: [
        'type1' => Type1::class,
        'type2' => Type2::class,
    ]);
} catch (HydrationFailure $e) {
    // handling
}

// Dehydration (excluding validation)
$dehydrator = new Dehydrator($cache);
try {
    $output = $dehydrator->dehydrate($object);
    $output = $dehydrator->dehydrateArray($arrayOfObjects);
} catch (DehydrationFailure $e) {
    // handling
}

// Validation
$validator = new Validator();
try {
    $validator->validate($object);
} catch (ValidationFailure $e) {
    // handling
}
```

## Runtime code loading and temp directory safety

Hydra executes generated PHP runtime code loaded from cache.

- `FileCache` stores generated code in files and loads it with `require`.
- `Psr16CacheAdapter` loads generated code from cache storage and executes it via `eval()`.

Because generated code is executable, treat cache storage as trusted runtime surface:

- keep cache backend private to your application process,
- do not share writable cache/temp directories with untrusted actors,
- prefer app-scoped directories over generic system temp locations in production,
- clear cache after deployments that change DTO structures.

## Errors handling

### Hydration

Use `getErrors` on Failure exception to get particular instance of `HydrationError` which will contain

- `code` - any backed enum
- `programmerMessage` - which will describe the error in programmer understandable way for debugging & logging purposes
- `key` - property, array key etc... where the error is, can be `string`, `int`, `bool`, or `null`
- `fullPath` - full path from root towards the property, array key etc... where the error is. For example `person.interests.0.name`

### Dehydration

Use `getErrors` to get particular instance of `DehydrationError` which will contain

- `code` - any backed enum
- `programmerMessage` - which will describe the error in programmer understandable way for debugging & logging purposes
- `key` - property, array key etc... where the error is, can be `string`, `int`, `bool`, or `null`
- `fullPath` - full path from root towards the property, array key etc... where the error is. For example `person.interests.0.name`

### Validation

Use `getErrors` to get particular instance of `ValidationError` which will contain

- `code` - any backed enum
- `programmerMessage` - which will describe the error in programmer understandable way for debugging & logging purposes
- `key` - property, array key etc... where the error is, can be `string`, `int`, `bool`, or `null`
- `fullPath` - full path from root towards the property, array key etc... where the error is. For example `person.interests.0.name`

## Detailed docs

- [Naming strategies](./docs/NamingStrategies.md)
- [Mappers](./docs/Mappers.md)
- [Validators](./docs/Validators.md)

## Potential future scope

- Serialization groups
- Lifecycle hooks
- Conditional Inclusion (`SkipWhen`, `IncludeIf`)
- Prefixed fields (`PrefixedWith`)


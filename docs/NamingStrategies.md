# Naming strategies

## Default (1:1)

```php
<?php declare(strict_types=1);

use DrabekDigital\Hydra\Attributes\NamingStrategy;
use DrabekDigital\Hydra\NamingStrategies\DefaultStrategy;

#[NamingStrategy(strategy: new DefaultStrategy())] // Optional
class Person
{
    public function __construct(
        public readonly string $foo,
        public readonly int $foo_bar,
        public readonly string $fooBar,
    ) {}
}
```

will be mapped from & to:

```json
{
    "foo": "foo",
    "foo_bar": 123,
    "fooBar": "foo@bar.com"
}
```

## UnderscoreCase

```php
<?php declare(strict_types=1);

use DrabekDigital\Hydra\Attributes\NamingStrategy;
use DrabekDigital\Hydra\NamingStrategies\UnderscoreCase;

#[NamingStrategy(strategy: new UnderscoreCase())]
class Person
{
    public function __construct(
        public readonly string $foo,
        public readonly int $fooBar,
    ) {}
}
```

will be mapped from & to:

```json
{
    "foo": "foo", 
    "foo_bar": 123,
}
```

Beware that specifying together `foo_bar` and `fooBar` will result in an error.

## Aliasing / renaming

When in need to support legacy fields names or different in output, you can use `Rename` attribute to alias the field name.

```php
<?php declare(strict_types=1);

use DrabekDigital\Hydra\Attributes\Rename;
use DrabekDigital\Hydra\Attributes\NamingStrategy;
use DrabekDigital\Hydra\NamingStrategies\UnderscoreCase;

#[NamingStrategy(strategy: new UnderscoreCase())]
class Person
{
    public function __construct(
        public readonly string $foo,
        #[Rename(from: 'foo_bar_old', to: 'fooBar2')]
        public readonly int $fooBar,
    ) {}
}
```

This will handle mapping from:

```json
{
    "foo": "foo",
    "foo_bar_old": 123,
}
```

And will map to:

```json
{
    "foo": "foo",
    "fooBar2": 123,
}
```

When `to` or `from` is not specified the property name will be used, however at least one of them must be specified.

## Custom

The naming strategy is direction aware so you can specify different strategies for different directions (hydration & dehydration).
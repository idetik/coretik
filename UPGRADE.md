# Upgrade guide

## From 1.x to 2.0

2.0 is released as prereleases first (`2.0.0-next.N`). To try one in a project:

```json
"require": {
    "idetik/coretik": "^2.0@beta"
}
```

Then run `composer update idetik/coretik -W`.

### Requirements

- PHP 8.2 or higher.

### Dependencies

coretik now requires `illuminate/collections` 11 or 12 (Laravel 10 no longer gets security fixes) and `nesbot/carbon` 3. If your project requires them directly, update its constraints.

**Carbon 3**: check your code using dates returned by coretik models (`getFieldAsDateTime()`, date casts…):

- `diffInDays()`, `diffInHours()`… return a `float` and are signed: `$past->diffInDays($now)` is positive, `$now->diffInDays($past)` is negative. Use `abs()` or `(int)` where you expected the 2.x behavior.
- `Carbon::createFromTimestamp()` creates dates in UTC. WordPress already sets PHP's default timezone to UTC, so nothing changes in a WordPress context.

See the [Carbon 3 migration guide](https://carbon.nesbot.com/docs/#api-carbon-3).

**Collections**: `Coretik\Core\Collection` extends `Illuminate\Support\Collection`. See the "Collections" sections of the [Laravel 11](https://laravel.com/docs/11.x/upgrade) and [Laravel 12](https://laravel.com/docs/12.x/upgrade) upgrade guides.

### Date metas

Date casts store dates with the WordPress database format, `Y-m-d H:i:s` (1.x used `Y-m-d H:i:s.u`, which could not read WordPress dates).

- Dates stored by 1.x, with microseconds, are still read.
- If you override `getDateFormat()` in a model, check its value.

### Unknown methods throw an exception

`App`, builders (`PostType`, `Taxonomy`…) and queries forward unknown methods to container services, attached macros and query builders. In 1.x, an unknown method returned `null`, so a typo broke the chain further, without a clear message. It now throws a `BadMethodCallException`:

```php
app()->shcema('product');                            // BadMethodCallException: no "shcema" service
app()->schema('product')->query()->limt(5);          // BadMethodCallException
```

### Queries

Queries have explicit, chainable methods for the parameters shared by all query builders: `set()`, `childOf()`, `all()`, `limit()`, `in()`, `notIn()` and `not()`. Builder specific methods (`page()`, `withoutMetas()`…) are still forwarded.

If one of your custom queriers defines a method with one of these names, its signature must match:

```php
public function set(string $key, $value): static;
public function childOf(int|array $values): static;
public function all(): static;
public function limit(int $number): static;
public function in(array $ids): static;
public function notIn(array $ids): static;
public function not(int $id): static;
public function whereMeta(...$args);
public function whereTax(...$args);
```

### Interfaces

Interfaces now declare the methods coretik calls on them. Classes extending coretik base classes (`Query`, `Model`, `Builder`, `Form`…) have nothing to do. If a class of your project implements one of these interfaces directly, add the missing methods:

| Interface | Added methods |
| --- | --- |
| `QuerierInterface` | `where()`, `orWhere()`, `whereMeta()`, `whereTax()`, `set()`, `childOf()`, `all()`, `limit()`, `in()`, `notIn()`, `not()`, `querier()`, `get()`, `ids()`, `models()`, `collection()`, `first()`, `count()`, `total()` |
| `ModelInterface` | `name()`, `on()`, `trigger()`, `save()`, `delete()` |
| `BuilderInterface` | `hasHandlerClassName()`; `handler()` accepts a class name |
| `WhereClauseInterface` | `toArray()` |
| `TaxonomiableInterface` | extends `BuilderInterface` |
| `Handlable` | `setConfigIfNotDefined()` |
| `Asyncable` | extends `Handlable`, `view()` |

New interfaces, implemented by the WordPress models: `MetableInterface` (models with declared metas) and `AcfFieldsInterface`.

### Schema and models

- `Schema::get()` returns `null` for an unknown type too (it failed with a PHP warning). `Schema::type()` returns an empty collection for an unknown type.
- New `Schema::modelable($name, $type)`: a modelable builder, or a `ContainerValueNotFoundException`.
- `PostModel::parent()` and `TermModel::parent()` return `null` without parent (1.x loaded a model with id 0). Their return type is `?Model`: the parent class is the one of the builder factory.
- `Model::create()` returns `static`.
- `belongsTo()` throws an `UnhandledException` for models other than posts, terms and comments (1.x failed on an undefined variable).

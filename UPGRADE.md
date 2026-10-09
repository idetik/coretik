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

# FancyCrud

Abstract building blocks for well-structured Laravel projects: base controllers, services, repositories, CRUD actions, policies, requests, resources, enums, casts and traits.

## Install

```bash
composer require fancycrud/fancy-crud
php artisan vendor:publish --tag=fancy-crud-config
```

## Structure

| Namespace | Contents |
|---|---|
| `FancyCrud\Actions\Crud` | `AbstractAction`, `CreateAction`, `UpdateAction`, `DestroyAction` |
| `FancyCrud\Services` | `BaseService`, `BaseCrudService` |
| `FancyCrud\Repositories` | `BaseRepository` |
| `FancyCrud\Contracts` | `Controller`, `CrudController`, `BasePolicy`, `BaseRequest`, `BaseResource`, `BaseEnumTrait`, `BaseNotification` |
| `FancyCrud\Casts` | `MediaCast` |
| `FancyCrud\Traits` | `Activable`, `HasSlug`, `Sortable`, `ResponseTrait`, `PaginationMetaTrait` |
| `FancyCrud\Middleware` | `AcceptedLanguagesMiddleware` |

`BaseNotification` needs `laravel-notification-channels/fcm`.

## Testing

```bash
composer install && composer test
```
## Testing your CRUD endpoints (Pest)

The package ships a declarative test generator, `FancyCrud\Testing\CrudTestBuilder`
(helper: `crudTest()`), that expands into the standard suite for a `CrudController`.

1. Use `InteractsWithCrud` on the base test case your CRUD files run on, call
   `$this->setUpCrud()` at the end of `setUp()`, and implement `crudUser(?array $permissions)`
   (`null` = may do everything, array = exactly those permissions, e.g. `['index posts']`).
   Token-based apps override `crudHeaders()`, `crudUnauthedHeaders()`, `crudRestrictedHeaders()`;
   apps needing an environment (tenant, transaction) override `crudSeed()`.
2. Bind the directory in `tests/Pest.php`: `pest()->extend(CrudTestCase::class)->in('Feature/Crud');`
3. One file per controller:

```php
crudTest('posts')
    ->model(Post::class)
    ->all()                                  // or ->only(['index','store',...]) / ->except([...])
    ->createPayload(['title' => 'Hello'])
    ->invalidPayload(['title' => ''])
    ->jsonStructure(['id', 'title'])
    ->assertCreated(['title' => 'Hello'])
    ->importRows([['Title'], ['First'], ['Second']])   // for the import verb (CSV upload)
    ->run();
```

Verbs: `index store show update patch destroy export template import`, plus always
401 (unauthenticated) and 403 (no permission; `->restrictedPermission(null)` to skip).
Defaults: base URL `/api` (`->baseUrl()`), records from `Model::factory()` (or `->record(fn)`).
See `tests/` for a complete working example (fixture controller, policy, Excel sheet).

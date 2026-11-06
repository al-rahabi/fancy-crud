# Architecture

## Layering

```
HTTP request
   │
   ▼
CrudController (Contracts)        ← routing target; formats JSON; authorizes reads AND writes; before/after hooks
   ├── BaseRepository             ← READ side: index/show queries, search, filters, ordering, pagination
   └── BaseCrudService            ← WRITE side: store/update/destroy orchestration + hooks
          └── CreateAction / UpdateAction / DestroyAction   ← one class per write verb, authorize + run hooks
Policy (BasePolicy) + Spatie permissions  ← "{action} {resource}" strings
BaseResource                      ← model → array (default/detailed/platform/abilities)
BaseRequest                       ← validation rules per HTTP method
BaseExcel + ExcelHeader           ← sheet definition, used by ExportTrait / ImportTrait on the controller
```

Key split: **repository reads, service writes**. `BaseCrudService::findItem()` is only a bare lookup used by
update/destroy; eager-loaded relations belong to the repository.

## Directory map (`src/`)

| Path | Group | Purpose |
|------|-------|---------|
| `Actions/Crud/` | crud-core | `AbstractAction`, `CreateAction`, `UpdateAction`, `DestroyAction` |
| `Services/` | crud-core | `BaseService` (platform), `BaseCrudService` (request-free, user-free writes) |
| `Repositories/` | crud-core | `BaseRepository` (index/show query builder) |
| `Contracts/` | http / authorization / support | `Controller`, `CrudController`, `BaseRequest`, `BaseResource`, `BasePolicy`, `BaseEnumTrait`, `BaseNotification` |
| `Traits/` | support / http | `ResponseTrait`, `PaginationMetaTrait`, `Activable`, `Sortable`, `HasSlug`, `Makeable` |
| `Middleware/` | http | `AcceptedLanguagesMiddleware` |
| `Casts/` | support | `MediaCast` |
| `Filters/` | crud-core | `BaseFilters`, `Filter`, `FilterSet` |
| `Table/` | table | `BaseTable`, `Column` |
| `Bulk/` | bulk | `BulkAction`, `HandlesByQuery/Sync/Job`, `BulkRunner`, `BulkActionJob` |
| `Excel/` | excel | `BaseExcel`, `ExcelHeader`, `ExcelImportResult`, `Contracts/`, `Enums/`, `Exports/`, `Imports/`, `Managers/`, `Traits/` |
| `Support/` | authorization | `PermissionName` (shared permission-name derivation) |
| `Permissions/` | authorization | `PermissionSyncer` |
| `Console/` | authorization | `SyncPermissionsCommand` (`fancy-crud:permissions`) |
| `Testing/` | testing | `CrudTestBuilder`, `InteractsWithCrud`, `InteractsWithPermissions`, `helpers.php` (`crudTest()`) |
| `resources/skills/fancy-crud/` | skill | AI code-rules skill, publishable with tag `fancy-crud-skills` |
| `helpers.php` | support | `assetUrl()` |
| `FancyCrudServiceProvider.php` | root | auto-discovered; merges config, loads lang, registers command, publish tags |

Outside `src/`: `config/fancy-crud.php`, `resources/lang/{en,ar}/messages.php`, `tests/` (Pest + Testbench fixtures).

## Service provider

`FancyCrudServiceProvider` (registered through `composer.json` `extra.laravel.providers`):
- `register()`: `mergeConfigFrom(config/fancy-crud.php, 'fancy-crud')`
- `boot()`: `loadTranslationsFrom(resources/lang, 'fancy-crud')`; in console also registers `SyncPermissionsCommand` and
  publish tags `fancy-crud-config` and `fancy-crud-lang`.

Autoloaded files (composer `autoload.files`): `src/helpers.php`, `src/Testing/helpers.php`.

## Dependencies

Required: PHP ^8.3, `laravel/framework` ^11|12|13, `guzzlehttp/psr7`, `maatwebsite/excel` ^3.1,
`spatie/laravel-permission` ^6|^7, `laravel-notification-channels/fcm` ^6.2 (for `BaseNotification`). Dev: Testbench, Pest, Pint.

## Design rules visible in the code

1. Everything is `abstract`/overridable with small hook methods; concrete apps override config properties
   (`$model`, `$resource`, `$excel`, …) and hooks, not whole methods.
2. Controllers hold **no business logic**: they resolve a repository + service, validate through a request class,
   call the service, and wrap results with `ResponseTrait`.
3. Authorization is the controller's alone: every read, write and bulk action goes through Laravel's Gate/Policy
   (`$this->authorize()`), before validation and before the service. Services, actions, repositories and jobs never authorize
   and never read the request or the user. Policies end up on the `"{action} {resource}"` permission strings.
4. Permission names are always `"{action} {resource}"` where resource is the plural snake-cased model name with spaces
   (`BlogPost` → `blog posts`).
5. Excel is optional per controller: no `$excel` class ⇒ the endpoints 404.
6. Helpers are guarded with `function_exists` so host apps can override them.

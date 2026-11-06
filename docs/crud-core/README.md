# crud-core — Services, Actions, Repositories

Source: `src/Services/`, `src/Actions/Crud/`, `src/Repositories/`.

## `BaseService` (`FancyCrud\Services\BaseService`)
A service holds business logic only: **no request, no authenticated user, no authorization, no policies** — all of that is the controller's job
(the acting user, if needed, is passed in as data). It can run from a controller, a console command or a queued job alike.
- `protected string $platform` — free-form client label (e.g. `mobile`, `dashboard`).
- `initialize()` — empty hook called at the end of the constructor.
- `static instance($platform)`.

## `BaseCrudService` (extends `BaseService`)
Owns the **write** side. Configure with `protected string $model = Post::class;`.

| Method | Role |
|--------|------|
| `store(array)` / `update(id\|Model, array)` / `destroy(id\|Model)` | Entry points; each instantiates the matching Action |
| `findItem(id\|Model)` | Bare `globalQuery()->find()` used by update/destroy |
| `globalQuery()` | `$model::query()` — override to scope writes (tenant, soft deletes…) |
| `storeAdditionalData($data)` / `updateAdditionalData($data)` | Pure attributes derived from the data itself (e.g. a slug), merged into the payload (default `[]`). No request, user or side effects |
| `createMethod($data)` / `updateMethod($item, $data)` | The actual persistence; override for custom writes |

There are **no before/after hooks** on the service. Logic around a write (checks, events, notifications, audit) belongs to the controller's
`beforeStore/afterStore/beforeUpdate/afterUpdate/beforeDestroy/afterDestroy` hooks — see [http](../http/README.md). Store is just:
`storeAdditionalData` → `createMethod`.

## Actions (`FancyCrud\Actions\Crud`)
`AbstractAction(BaseCrudService $service, array $payload = [])`
- `for($idOrModel)` sets the target; `execute()` is abstract.
- `item()` resolves the target through `service->findItem()`. Actions never authorize and have no hooks.

| Action | Returns | Behaviour |
|--------|---------|-----------|
| `CreateAction` | `Model` | merge `storeAdditionalData` → `createMethod` |
| `UpdateAction` | `?Model` | resolve (null if missing) → merge `updateAdditionalData` → `updateMethod` |
| `DestroyAction` | `bool` | resolve (false if missing) → `delete()` |

Models can opt into business rules by defining `canShow/canUpdate/canDelete($user)`; `BasePolicy` calls them, so a `false` becomes a **403** from the controller's `authorize()` (it used to be a 404 from inside the action).

## `BaseRepository`
Owns the **read** side. Configuration properties:

| Property | Default | Meaning |
|----------|---------|---------|
| `$model` | `Model::class` | Eloquent class |
| `$paginated` | `true` | paginate index |
| `$limit` | `10` | page size, overridden by `?per_page=` |
| `$indexRelation` / `$indexCountRelation` | `[]` | `with()` / `withCount()` for index |
| `$showRelation` / `$showCountRelation` | `[]` | extra for show (show relations are merged with index relations) |

| `$filters` | `null` | `BaseFilters` class of the resource (see Filters below) |
| `$table` | `null` | `BaseTable` class: column-driven index, see [table](../table/README.md) |
| `$bulkActions` | `[]` | `BulkAction` classes served by `POST …/bulk`, see [bulk](../bulk/README.md) |

Extra seams: `table()`, `requestedFields()` (`?fields`), `resolvedFilters()` (repository + table filters), `bulkActions()`,
`bulkQuery(array $selection)`, `getModel()` (falls back to the table's model when `$model` is not set).

Query hooks to override: `globalQuery()`, `customShowQuery(Builder)`, `searchableArray()`, `defaultOrdering(Builder)`,
`orderingFields()`, `filters()`, plus request accessors `getLimit()`, `forceUnlimited()`
(`?unlimited`), `getSearchTerm()` (`?search`), `requestedOrdering()` (`?order_by[]`), `requestedFilters()`.

### Parameters (no request access)
The repository never reads the request. `CrudController::index()` calls `withParameters($validated)` with what `BaseIndexRequest` validated
(`per_page`, `unlimited`, `search`, `fields`, `order_by`, filter values); the accessors `getLimit()`, `forceUnlimited()`, `getSearchTerm()`,
`requestedFields()`, `requestedOrdering()`, `requestedFilters()` read those. `indexRules()` returns the filters' validation rules
(`Filter::rules()`) for the index request. Outside HTTP (tests, commands) call `withParameters([...])` yourself.

### Search
`applySearch`: if `?search=` present, `OR … LIKE %term%` over `searchableArray()` (defaults to the model's `$fillable`);
`relation.field` entries search through `orWhereRelation`; also `orWhere(table.id = term)`.

### Filters
Filters are classes: extend `FancyCrud\Filters\BaseFilters`, return `Filter` objects from `filters()`, and set the repository's
`protected ?string $filters = PostFilters::class`.

```php
Filter::make('status');                              // where status = ?status
Filter::make('title')->condition('LIKE');            // where title LIKE ?title
Filter::make('created_at')->method('whereDate');
Filter::make('name')->relation('author');            // whereHas('author', …)
Filter::make('mine')->using(fn ($q, $v) => …);       // callback wrapped in a `where` group
Filter::make('type')->value('post');                 // fixed value, wins over the request
```
`name` is the request key and column. Built-in `forced_items` → `whereIn('id', …)` (override by declaring a filter with the same
name, or override `defaults()`). Filters with a null value are skipped; only declared names are read from the request.
Override `repository->filters()` to build the filter set dynamically.

### Ordering
`?order_by[0][name]=title&order_by[0][direction]=desc`. Only names declared in `orderingFields()` are honoured; each entry
may be a column string, `['field'=>…, 'direction'=>…]`, or a callable `fn(Builder, $direction)`. No `order_by` ⇒ `defaultOrdering` (`id desc`).

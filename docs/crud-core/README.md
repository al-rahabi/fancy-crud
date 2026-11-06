# crud-core — Services, Actions, Repositories

Source: `src/Services/`, `src/Actions/Crud/`, `src/Repositories/`.

## `BaseService` (`FancyCrud\Services\BaseService`)
- `protected string $guard = 'web'` — guard used to resolve the user. `getUser()` is **lazy** (`$this->user ??= auth($this->guard)->user()`); the constructor also tries once.
  Override `$guard` in a subclass for other guards (e.g. `'admin'`).
- `protected string $platform` — free-form client label (e.g. `mobile`, `dashboard`) passed to resources.
- `initialize()` — empty hook called at the end of the constructor.
- `getUser()`, `static instance($platform)`.

## `BaseCrudService` (extends `BaseService`)
Owns the **write** side. Configure with `protected string $model = Post::class;`.

| Method | Role |
|--------|------|
| `store(array)` / `update(id\|Model, array)` / `destroy(id\|Model)` | Entry points; each instantiates the matching Action |
| `findItem(id\|Model)` | Bare `globalQuery()->find()` used by update/destroy |
| `globalQuery()` | `$model::query()` — override to scope writes (tenant, soft deletes…) |
| `getPermissionName($action)` | `PermissionName::for($model, $action)` — e.g. `update blog posts` |
| `storeAdditionalData($data)` / `updateAdditionalData($data)` | Return extra attributes merged into the payload (default `[]`) |
| `beforeStore` / `beforeUpdate` → `beforeSave` | Pre-write hooks (no return) |
| `afterStore` / `afterUpdate` → `afterSave($model, $data)` | Post-write hooks |
| `beforeDelete($item)` / `afterDelete()` | Delete hooks |
| `createMethod($data)` / `updateMethod($item, $data)` | The actual persistence; override for custom writes |

Hook order for store: `authorize create` → `storeAdditionalData` → `beforeStore` → `createMethod` → `afterStore`.

## Actions (`FancyCrud\Actions\Crud`)
`AbstractAction(BaseCrudService $service, array $payload = [])`
- `for($idOrModel)` sets the target; `execute()` is abstract.
- `authorize($permission, $model = null)`: if the service has a user (resolved lazily) and `!$user->can($service->getPermissionName($permission), $model)` → `abort(403)`. No user ⇒ no check.

| Action | Returns | Behaviour |
|--------|---------|-----------|
| `CreateAction` | `Model` | authorize → merge extra data → hooks → create |
| `UpdateAction` | `?Model` | resolve (null if missing) → authorize → `canUpdate()` on model if present → hooks → update |
| `DestroyAction` | `bool` | resolve (false if missing) → authorize → `canDelete()` on model if present → hooks → delete |

Models can opt into business rules by defining `canUpdate()` / `canDelete()` (a `false` becomes a 404 through the controller).

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

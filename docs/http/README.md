# http — Controllers, Requests, Resources, Responses, Middleware, Translations

Source: `src/Contracts/{Controller,CrudController,BaseRequest,BaseResource}.php`, `src/Traits/{ResponseTrait,PaginationMetaTrait}.php`,
`src/Middleware/`, `resources/lang/`.

## `Controller` (`FancyCrud\Contracts\Controller`)
Abstract base with `AuthorizesRequests`, `DispatchesJobs`, `ValidatesRequests`, `ResponseTrait`.
Constructor sets `$this->service = $this->service()`; subclasses implement `service(): BaseService|BaseCrudService`.
Use it directly for non-CRUD endpoints that still want the service + response envelope.
`protected string $guard = 'web'` and `user(): ?Authenticatable` (`auth($guard)->user()`) — the controller is the only layer that reads the user,
to authorize and to pass the actor into services as plain data.

## `CrudController` (extends `Controller`, implements `Exportable`, `Importable`, uses `ExportTrait`, `ImportTrait`)
Properties to set in the concrete class:

| Property | Type | Role |
|----------|------|------|
| `$resource` | `?string` | `BaseResource` subclass used to render items |
| `$storeRequest` / `$updateRequest` | `?string` | `BaseRequest` classes resolved via `app()` |
| `$excel` | `?string` | `BaseExcel` subclass; null disables export/import (404) |
| `$indexRequest` | `class-string<BaseIndexRequest>` | validates index query parameters (default `BaseIndexRequest`) |
| `$bulkRequest` | `class-string<BaseBulkRequest>` | validates the bulk payload (default `BaseBulkRequest`) |

Abstract methods to implement: `service()`, `repository(): BaseRepository`, `getPlatform(): string`
(forwarded to resources so they can switch shapes per client).

Public actions: `index`, `show($id)`, `store`, `update($id)`, `destroy($id)`, `bulk` (404 unless the repository has `$bulkActions`),
plus `export`, `import`, `importTemplate` from the traits. `index` returns table rows (no Resource) when the repository has a `$table`.
**Writes follow one short shape** — authorize → validated data → `before*` hook → one service call → `after*` hook → respond:

```php
public function store(): JsonResponse
{
    $this->authorize('create', $this->repository->getModel());
    $data = $this->storeData();
    $this->beforeStore($data);
    $item = $this->service->store($data);
    $this->afterStore($item, $data);

    return $this->success($this->formatItem($item), 201);
}
```
`update`/`destroy` first resolve the record with `service->findItem($id)` (404), then `authorize('update'|'delete', $item)` (403).
Controller hooks to override (all no-ops, `protected`): `beforeStore(array $data)`, `afterStore(Model $item, array $data)`,
`beforeUpdate(Model $item, array $data)`, `afterUpdate(Model $item, array $data)`, `beforeDestroy(Model $item)`, `afterDestroy(Model $item)`.
Data seams: `storeData()` / `updateData()` return the validated payload — override to add server-side values, e.g.
`[...parent::storeData(), 'created_by' => $this->user()?->getAuthIdentifier()]`.
Authorization comes first, so a forbidden user gets 403 before validation or any hook runs.

Overridable seams: `authorizeIndex()` (swap the policy check for a permission-string check when a shared model makes
policies ambiguous), `authorizeShow($item)`, `formatItem($item)`.

Routing note: register `export`, `import/template`, `import`, `bulk` (`Route::post('posts/bulk', …)`) **before** `apiResource` so `{id}` does not capture them.
The fixtures use `->parameters(['posts' => 'id'])` so the route parameter is named `id`.

## `BaseIndexRequest` / `BaseBulkRequest` (extend `FormRequest`)
Nothing reads the raw request: the controller passes only validated values on.
- `BaseIndexRequest` validates `per_page` (int ≥ 1), `unlimited` (bool), `search` (string ≤ 255), `fields` (string or list of strings),
  `order_by.*.name` / `order_by.*.direction` (`asc|desc`) and, through `CrudController::indexRules()`, one rule set per declared filter
  (`Filter::rules()`, default `nullable`; `forced_items` is `nullable|array`). Keys without a rule never reach the repository. The route's
  controller is found with `$this->route()->getController()`, so the request needs no wiring; subclass it to add rules.
- `BaseBulkRequest` validates `action`, exactly one of `ids` / `filters` / `all`, filter values (`filters.<name>` by `Filter::rules()`) and `extra.<key>`
  by the chosen action's `rules()` (through `CrudController::bulkRules()`); the selection check runs in `withValidator()`.

## `BaseRequest` (extends `FormRequest`)
`rules()` dispatches on HTTP method: `POST → storeRules()`, `PUT → updateRules()`, `PATCH → patchRules()`, else `commonRules()`.
`storeRules`/`updateRules` default to `commonRules()`. `patchRules()` derives from `updateRules()` by dropping `required`
(array rules) or replacing it with `nullable` (string rules). Override `commonRules()` for shared rules.

## `BaseResource` (extends `JsonResource`)
`toArray()` = `toDefaultArray()` + (when detailed) `toDetailedArray()` + `abilities()` (added only when non-empty).
- `toDefaultArray`: if `$platform` is set and a method `to{Platform}Array($request, $resource)` exists it is used, else `$this->resource->toArray()`.
- Static builders: `makeWithDetail($resource, $user, $detailed=true, $platform='')` (single) and
  `makeCollection($resource, $user, $detailed=false, $platform='')` (collection/paginator; returns a Collection or the paginator with mapped items).
- Fluent: `withDetail()`, `withPlatform()`, `setUser()`, `withAbility($user, $bool)`.
- Override `abilities()` to expose per-item `can` flags (use `$this->user?->can('update', $this->resource)`).

## `ResponseTrait`
`response($data, $code, $message, $meta)` builds the envelope (`status` true for 200/201/204/205). Helpers: `success()` (default 200),
`error()` (default 400), `notFound()` (404, `data: null`). Messages default to `__('fancy-crud::messages.success|error|not_found')`.

## `PaginationMetaTrait`
`paginationMeta(LengthAwarePaginator)` returns the same meta block `CrudController::index()` builds — for hand-written controllers.

## `AcceptedLanguagesMiddleware`
HTML requests read locale from session `locale`; others from the `Accept-Language` header (default `en`). Locale must be in `fancy-crud.supported_locales`, else `app.supported_locales`, else `[app.locale]`; if not, `app.locale` is used.

## Translations (`fancy-crud::messages`)
`resources/lang/{en,ar}/messages.php`: `success`, `error`, `not_found` (used by `ResponseTrait`) and the Excel keys
`excel_import_finished`, `excel_no_matching_columns`, `excel_row_failed`. Publish with `--tag=fancy-crud-lang` to override.

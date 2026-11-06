# http — Controllers, Requests, Resources, Responses, Middleware, Translations

Source: `src/Contracts/{Controller,CrudController,BaseRequest,BaseResource}.php`, `src/Traits/{ResponseTrait,PaginationMetaTrait}.php`,
`src/Middleware/`, `resources/lang/`.

## `Controller` (`FancyCrud\Contracts\Controller`)
Abstract base with `AuthorizesRequests`, `DispatchesJobs`, `ValidatesRequests`, `ResponseTrait`.
Constructor sets `$this->service = $this->service()`; subclasses implement `service(): BaseService|BaseCrudService`.
Use it directly for non-CRUD endpoints that still want the service + response envelope.

## `CrudController` (extends `Controller`, implements `Exportable`, `Importable`, uses `ExportTrait`, `ImportTrait`)
Properties to set in the concrete class:

| Property | Type | Role |
|----------|------|------|
| `$resource` | `?string` | `BaseResource` subclass used to render items |
| `$storeRequest` / `$updateRequest` | `?string` | `BaseRequest` classes resolved via `app()` |
| `$excel` | `?string` | `BaseExcel` subclass; null disables export/import (404) |

Abstract methods to implement: `service()`, `repository(): BaseRepository`, `getPlatform(): string`
(forwarded to resources so they can switch shapes per client).

Public actions: `index`, `show($id)`, `store`, `update($id)`, `destroy($id)`, plus `export`, `import`, `importTemplate` from the traits.
Overridable seams: `authorizeIndex()` (swap the policy check for a permission-string check when a shared model makes
policies ambiguous), `authorizeShow($item)`, `formatItem($item)`.

Routing note: register `export`, `import/template`, `import` **before** `apiResource` so `{id}` does not capture them.
The fixtures use `->parameters(['posts' => 'id'])` so the route parameter is named `id`.

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

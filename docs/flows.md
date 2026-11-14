# Request flows

All controllers extend `FancyCrud\Contracts\CrudController`; the constructor calls `service()` and `repository()`
(both abstract, supplied by the concrete controller). Every success body uses the envelope from `ResponseTrait`:

```json
{ "data": ..., "status": true, "message": "...", "code": 200, "meta": {} }
```

## index — `GET /resource`
1. `authorizeIndex()` → `$this->authorize('index', $repository->getModel())` (policy `index`).
1b. `indexParameters()` = `app($indexRequest)->validated()` (`BaseIndexRequest`: `per_page`, `unlimited`, `search`, `fields`, `order_by`, filter values) →
   `$repository->withParameters($params)`. The repository reads only these, never the request.
2. `BaseRepository::index()`: `globalQuery()->with(getRelations())->withCount(getCountRelations())`
   → `applyFilters` → `applySearch` → `applyOrder` → paginate (`per_page` request key, default `$limit = 10`, clamped to `$maxLimit = 100`)
   or `get()` when `$paginated = false` or `?unlimited=1` (only if the repository sets `$allowUnlimited = true`).
3. `BaseResource::makeCollection(items, user, detailed=false, platform)`.
4. Response `data` = items; `meta` = `current_page,total,per_page,last_page,pages_count` for paginators.

**With a table** (`$table` on the repository, see [table](table/README.md)): step 2 becomes `globalQuery()` →
`BaseTable::prepare()` (select/with/withCount from the requested `fields`, default columns when none) → filters (repository + column
filters) → search → order (table ordering merged with `orderingFields()`) → paginate. Step 3 becomes `BaseTable::rows()` (no Resource);
`meta.table` carries `columns`, `selected` and `bulk_actions`.

## bulk — `POST /resource/bulk`
1. 404 if the repository has no `$bulkActions`.
2. `app($bulkRequest)->validated()` (`BaseBulkRequest`: `action` known, exactly one of `ids` / `filters` / `all`, filter values by `Filter::rules()`, `extra` by the action's `rules()`; 422) → `BulkRunner::resolve()`.
3. `authorize($action->ability(), $model)` (403).
4. `BulkRunner::run()`: `query` → `byQuery($query, $extra)`; `sync` → chunked `bySync` per model; `job` → `BulkActionJob` dispatched (chunked `byJob` inside).
   The selection query comes from `BaseRepository::bulkQuery()`. See [bulk](bulk/README.md).

## show — `GET /resource/{id}`
1. `BaseRepository::find($id)` applies `customShowQuery`, show relations (show + index relations) and counts.
2. `null` → `notFound()` (404, `data: null`).
3. `authorizeShow($item)` → policy `show`.
4. `formatItem()` → `BaseResource::makeWithDetail(item, user, detailed=true, platform)`.

## store — `POST /resource`
1. Controller: `authorize('create', Model)` (policy, 403) — before anything else.
2. `storeData()` = `app($storeRequest)->validated()` (422 on failure); override to add server-side values such as the acting user.
3. `beforeStore($data)` (controller hook) → `BaseCrudService::store($data)` → `CreateAction`: merge `storeAdditionalData()` → `createMethod`
   → `afterStore($item, $data)` (controller hook).
4. Response 201 with `formatItem($created)`.

## update — `PUT|PATCH /resource/{id}`
1. Controller: `service->findItem($id)` → `null` ⇒ 404 → `authorize('update', $item)` (policy, which also runs the model's `canUpdate($user)`; 403).
2. `updateData()` = `app($updateRequest)->validated()`. `BaseRequest::rules()` picks `updateRules()` for PUT and `patchRules()` for PATCH
   (patch = update rules with `required` removed / turned into `nullable`).
3. `beforeUpdate($item, $data)` → `service->update($item, $data)` (`UpdateAction`: `updateAdditionalData` → `updateMethod`) → `afterUpdate($item, $data)`.

## destroy — `DELETE /resource/{id}`
Controller: `findItem` → null ⇒ 404 → `authorize('delete', $item)` (403) → `beforeDestroy($item)` → `service->destroy($item)` (`DestroyAction`: `delete()`)
→ `afterDestroy($item)` if it deleted (else a 400 error). Success returns the empty envelope.

## export — `GET /resource/export` (needs `$excel`)
`ExportTrait::export()` → `authorizeExcel('export')` (404 if no `$excel`; policy `export`) →
`ExcelExportManager::forController()` takes `exportQuery()` (default `repository->globalQuery()`), calls
`prepareExport($query)` → `BaseExcel::beforeExport`, collects headers, file name `<plural-kebab-model>-<Y-m-d>`,
chunk size → `download()` builds `ResourceExport` (FromQuery, chunked, auto-sized, bold header, date formats).
Query is ordered by primary key if unordered (chunk safety).

## import template — `GET /resource/import/template`
Gated by the `import` ability; returns a one-row sheet (`TemplateExport`) with a label per importable header and an example value.

## import — `POST /resource/import`
1. `authorizeExcel('import')`; validate `file` (xlsx/csv/ods, ≤ 20480 KB) + `BaseExcel::requestRules()`.
2. `excel->withContext(other fields)`, `beforeImport()`.
3. `ExcelImportManager::forController()->limit(importRowLimit)->import(file)` → `ResourceImport` (OnEachRow, chunked):
   - first row: `prepareImport(row)` → `withHeadingRow`, then headers/rules read, columns matched by **label first, key second**
     (case-insensitive); none matched ⇒ 400.
   - each row: empty ⇒ `skipped`; past row limit ⇒ ignored; else cast (`ExcelColumnType::castForImport`), `importUsing`,
     merge `additionalData`, validate with `rules()`; failures recorded with the sheet line number, never aborting;
     valid rows persisted in a DB transaction through `createForExcel()` if the controller defines it, else
     `storeExcelRow()` → `BaseExcel::create()`.
4. `afterImport($result)`; response `{imported, failed, skipped, errors:[{line, messages}]}`.

## Authorization of one request (summary)
```
index/show/export/import → Gate::authorize(ability, Model|instance) → Policy method → $user->can("{ability} {resource}")
store/update/destroy/bulk → the same, called by the controller *before* validation and before the service
```
Services, actions, repositories and jobs never authorize and never read the user. Keep routes behind auth middleware: a request without a
user is stopped by the auth middleware (401) and denied by the policy (403).

## Permission sync — `php artisan fancy-crud:permissions [--guard=web --guard=admin]`
`SyncPermissionsCommand` → `PermissionSyncer::sync()` → per configured guard: `updateOrCreate` every `"{action} {resource}"`,
delete the guard's permissions that were not just created/updated, give the guard's main role all of them, then create extra roles; Spatie cache is forgotten at the end.

## Generated test flow (`crudTest()`)
`crudTest('posts')->model(...)->...->run()` registers one Pest `test()` per verb. Each test seeds data via `crudSeed()`,
calls the endpoint with `$this->headers`, and asserts envelope/status; plus 401 and 403 tests. See [testing/](testing/README.md).

# Request flows

All controllers extend `FancyCrud\Contracts\CrudController`; the constructor calls `service()` and `repository()`
(both abstract, supplied by the concrete controller). Every success body uses the envelope from `ResponseTrait`:

```json
{ "data": ..., "status": true, "message": "...", "code": 200, "meta": {} }
```

## index — `GET /resource`
1. `authorizeIndex()` → `$this->authorize('index', $repository->getModel())` (policy `index`).
2. `BaseRepository::index()`: `globalQuery()->with(getRelations())->withCount(getCountRelations())`
   → `applyFilters` → `applySearch` → `applyOrder` → paginate (`per_page` request key, default `$limit = 10`)
   or `get()` when `$paginated = false` or `?unlimited=1`.
3. `BaseResource::makeCollection(items, user, detailed=false, platform)`.
4. Response `data` = items; `meta` = `current_page,total,per_page,last_page,pages_count` for paginators.

## show — `GET /resource/{id}`
1. `BaseRepository::find($id)` applies `customShowQuery`, show relations (show + index relations) and counts.
2. `null` → `notFound()` (404, `data: null`).
3. `authorizeShow($item)` → policy `show`.
4. `formatItem()` → `BaseResource::makeWithDetail(item, user, detailed=true, platform)`.

## store — `POST /resource`
1. `app($storeRequest)->validated()` (422 on failure).
2. `BaseCrudService::store($data)` → `new CreateAction($service, $data)->execute()`:
   `authorize('create')` → merge `storeAdditionalData()` → `beforeStore` (→`beforeSave`) → `createMethod` (`Model::create`)
   → `afterStore` (→`afterSave`).
3. Response 201 with `formatItem($created)`.

## update — `PUT|PATCH /resource/{id}`
1. `app($updateRequest)->validated()`. `BaseRequest::rules()` picks `updateRules()` for PUT and `patchRules()` for PATCH
   (patch = update rules with `required` removed / turned into `nullable`).
2. `UpdateAction`: resolve item (`findItem`) → `null` ⇒ service returns null ⇒ controller 404 →
   `authorize('update', $item)` → model `canUpdate()` check if defined (false ⇒ null ⇒ 404) →
   `updateAdditionalData` → `beforeUpdate` → `updateMethod` → `afterUpdate`.

## destroy — `DELETE /resource/{id}`
`DestroyAction`: find → null ⇒ false ⇒ 404 → `authorize('delete', $item)` → model `canDelete()` (false ⇒ 404) →
`beforeDelete` → `$item->delete()` → `afterDelete` if it deleted. Success returns the empty envelope.

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
store/update/destroy     → AbstractAction::authorize → $user->can("{action} {resource}", $model)  (abort 403)
```
`AbstractAction::authorize` is skipped entirely when the service has no user (`getUser()` null, resolved lazily from the guard).

## Permission sync — `php artisan fancy-crud:permissions [--guard=web --guard=admin]`
`SyncPermissionsCommand` → `PermissionSyncer::sync()` → per configured guard: `updateOrCreate` every `"{action} {resource}"`,
delete the guard's permissions that were not just created/updated, give the guard's main role all of them, then create extra roles; Spatie cache is forgotten at the end.

## Generated test flow (`crudTest()`)
`crudTest('posts')->model(...)->...->run()` registers one Pest `test()` per verb. Each test seeds data via `crudSeed()`,
calls the endpoint with `$this->headers`, and asserts envelope/status; plus 401 and 403 tests. See [testing/](testing/README.md).

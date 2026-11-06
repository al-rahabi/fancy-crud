# guides

## Add a CRUD resource end to end (in a host app)
1. Model `Post` (optionally with `canUpdate()` / `canDelete()`, traits `Activable`, `Sortable`, `HasSlug`).
2. `PostRepository extends BaseRepository` — `$model`, relations, `filterFields()`, `orderingFields()`, `searchableArray()`.
3. `PostService extends BaseCrudService` — `$model`, optional `$guard` and hooks.
4. `PostRequest extends BaseRequest` — `commonRules()` (+ `storeRules()` / `updateRules()` when they differ; PATCH is derived).
5. `PostResource extends BaseResource` — optional `toDetailedArray()`, `abilities()`, `to{Platform}Array()`.
6. `PostPolicy extends BasePolicy {}` and `Gate::policy(Post::class, PostPolicy::class)`.
7. `PostController extends CrudController` — set `$resource`, `$storeRequest`, `$updateRequest`, optional `$excel`; implement
   `service()`, `repository()`, `getPlatform()`.
8. Routes: `export`, `import/template`, `import` first, then `Route::apiResource('posts', PostController::class)->parameters(['posts' => 'id'])`
   (the controller methods take `$id`).
9. Permissions: add `'posts'` under the right guard in `fancy-crud.permissions.guards.*.resources`; run `php artisan fancy-crud:permissions`
   (creates/updates them, deletes that guard's undefined permissions, and gives its main role all of them).
10. Test: `crudTest('posts')->model(Post::class)->all()...->run()`.
11. (Optional) Excel: `PostExcel extends BaseExcel` with `headers()`.

Working reference: `tests/Fixtures/*`.

## Extension points cheat sheet
| Want to… | Override |
|----------|----------|
| Scope every read | `BaseRepository::globalQuery()` |
| Scope every write | `BaseCrudService::globalQuery()` |
| Add server-side attributes on create/update | `storeAdditionalData()` / `updateAdditionalData()` |
| React before/after writes | `beforeSave/afterSave`, `beforeStore/afterStore`, `beforeUpdate/afterUpdate`, `beforeDelete/afterDelete` |
| Custom persistence | `createMethod()` / `updateMethod()` |
| Different JSON per client | `BaseResource::to{Platform}Array()` + `getPlatform()` |
| Different index permission check | `CrudController::authorizeIndex()` |
| Different export rows | `exportQuery()` on the controller |
| Row import into several tables | `BaseExcel::create()` or controller `createForExcel()` |
| Different auth for tests (tokens) | `crudHeaders()`, `crudUnauthedHeaders()`, `crudRestrictedHeaders()` |

## Behaviours worth knowing (previously listed as quirks, now fixed)
- `Activable::toggleActive()` flips the real column value.
- `BaseService::getUser()` resolves `auth($guard)->user()` lazily (the constructor also tries once), so services built before login work.
  A request with **no user at all** still skips write authorization in `AbstractAction::authorize()` — deliberate, so console/queue code can use services.
  Protect HTTP routes with auth middleware.
- Read and write permission names both come from `FancyCrud\Support\PermissionName`.
- `ResponseTrait` messages come from `fancy-crud::messages.success|error|not_found` (en/ar shipped, publishable).
- `AcceptedLanguagesMiddleware` locales: `fancy-crud.supported_locales` → `app.supported_locales` → `[app.locale]`.
- `BaseNotification` stores the `$data` passed to its constructor; `laravel-notification-channels/fcm` is a required dependency.
- `MediaCast` checks files on `fancy-crud.media_disk` (not `storage/app/public`). Array writes drop missing files; a plain string is stored as given.
- `PermissionSyncer` **deletes** the permissions of a guard that are no longer in its definition (see authorization docs).

## Conventions for contributors
PHP 8.3, PSR-4 `FancyCrud\` → `src/`, format with `composer format` (Pint), run `composer test` (Pest). New helpers go inside
`function_exists` guards. Prefer adding hooks over changing signatures; keep `Excel/` free of HTTP concerns.

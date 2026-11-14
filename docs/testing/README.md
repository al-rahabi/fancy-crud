# testing — package tests and the test helpers it ships

## Shipped helpers (`src/Testing/`, autoloaded)
### `crudTest($resource)` → `CrudTestBuilder`
Declarative generator: one call at the top of a Pest file expands into one `test()` per verb.
```php
crudTest('posts')
    ->model(Post::class)
    ->all()                         // or ->only([...]) / ->except([...]); default verbs: index store show update destroy
    ->record(fn () => Post::create([...]))   // default: Model::factory()->create(), run inside crudSeed()
    ->createPayload([...])->updatePayload([...])->patchPayload([...])->invalidPayload([...])
    ->jsonStructure(['id','title'])->assertCreated(['title' => 'Hello'])->assertUsing(fn ($response, $model) => ...)
    ->importRows([['Title','Body'], ['First','one']], fields: [])   // CSV upload for the import verb
    ->baseUrl('/api')->missingId('999999999')->softDeletes()
    ->restrictedPermission(null)    // null skips the 403 test
    ->run();
```
Verbs: `index store show update patch destroy export template import`. Always generated: **401** (unauthenticated) and **403**
(user with no permissions; skipped with `restrictedPermission(null)`). Test names: `"{resource}: <what> (<status>)"`.
Properties are public because the generated closures are bound to the test case, not the builder.

### `InteractsWithCrud` (trait for the base test case)
Provides `$headers`, `setUpCrud()` (call at the end of `setUp()`), `crudHeaders()` (actingAs a full user), `crudUnauthedHeaders()`
(forget guards), `crudRestrictedHeaders($permissions)`, `crudSeed(Closure)` and the abstract `crudUser(?array $permissions): Authenticatable`
(`null` = may do everything, array = exactly those permissions). Token-based apps override the three header methods;
tenant/transaction apps override `crudSeed()`.

### `InteractsWithPermissions` (optional, for Spatie-backed users)
`grantPermissions(Model $user, ?array $permissions = null, ?string $guard = null)`:
guard defaults to `$user->getDefaultGuardName()` else the default auth guard. `null` ⇒ syncs that guard's config definition (this deletes the guard's undefined permissions) and grants every
permission of the guard; array ⇒ `findOrCreate` each name and `syncPermissions`. Clears Spatie's cache. Use inside `crudUser()`.

Bind a directory in `tests/Pest.php`: `pest()->extend(CrudTestCase::class)->in('Feature/Crud');`

## The package's own tests (`tests/`)
- `phpunit.xml`: sqlite in-memory; suites `Feature` and `Unit`. Run `composer test` (Pest).
- `tests/Pest.php`: `Feature` runs on `TestCase`, `Unit` runs on `SupportTestCase`.
- `tests/TestCase.php`: Orchestra Testbench; providers `FancyCrudServiceProvider` + Excel's; creates the `posts` table; registers
  `PostPolicy`; routes under `api/` guarded by `auth:web` (static `export`/`import` routes before `apiResource('posts')` with parameter `id`);
  `crudUser()` returns the in-memory fixture `User`.
- `tests/Fixtures/`: `Post` model, `PostController` (`CrudController`), `PostService`, `PostRepository`, `PostRequest`, `PostResource`,
  `PostExcel`, `PostPolicy extends BasePolicy`, `User` (in-memory; `can('verb resource')` answered from its permission list, `null` = all).
- `tests/Feature/PostCrudTest.php`: a single `crudTest('posts')->all()...` — 16 tests.
- `phpunit.xml` also has a `Unit` suite (`tests/Unit`) bound to `tests/SupportTestCase.php`: lightweight Testbench app with Spatie tables (stub migration), `users` and `toggles` tables.
  `PermissionsTest` covers the syncer (multi-guard, main role, pruning, idempotency), the command and `InteractsWithPermissions`;
  `SupportTest` covers `Activable`, `PermissionName`, lazy service user, response messages, middleware locales, notification data and `MediaCast`.
  Fixtures live in `tests/Unit/Fixtures/`.
- `tests/Unit/SkillTest.php`: the shipped skill (frontmatter, reference links, `fancy-crud-skills` publish tag).
- `tests/Feature/ControllerContractTest.php`: the refactored contract — controller hooks and their order (`HookedPostController`, route `hooked-posts`), authorization before
  validation and before any hook, no hooks on 403/422/404, services/actions running with nobody logged in and no request, index parameters validated by `BaseIndexRequest`.
- `tests/Feature/FiltersTest.php`: `BaseFilters`/`Filter` through `PostRepository` (`PostFilters`).
- `tests/Feature/TableTest.php`: the table builder on `table-posts` (`PostTable`, `PostTableRepository`, `PostTableController`, models `Author`/`Comment`):
  default and requested fields, query shape via the query log (selected columns, eager loads, counts), every column type, relation/count/getter/path
  values, ordering (plain, string, closure, count, non-orderable), column/table/repository filters, `describe()` meta, authorization, and that the viewer is an
  explicit input (abilities for the given user rather than the logged-in one, guest on `null`, user handed to closures, rendering with no `auth()`).
- `tests/Feature/BulkActionTest.php`: bulk actions (`PublishBulkAction` query, `DeleteBulkAction` sync, `ArchiveBulkAction` job) — selection kinds and their
  validation, extra data, the three modes (`Queue::fake()` and the sync queue), authorization, 404 without actions.
- `TestCase` also creates `authors`/`comments` tables and extra `posts` columns (`status`, `is_active`, `image`, `meta`, `author_id`); route `posts/bulk`
  (no actions ⇒ 404) and `table-posts` (+ `/bulk`). Run `composer test` for the current total (139 tests).

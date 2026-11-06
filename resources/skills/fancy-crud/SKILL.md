---
name: fancy-crud
description: Code rules and templates for Laravel apps built on al-rahabi/fancy-crud. Use whenever you create or change a controller, service, action, repository, form request, resource, policy, table, filter, bulk action or test that extends FancyCrud classes. Enforces thin controllers, authorization and before/after logic in the controller, request-free and user-free services, validated input only, no N+1 queries, strong typing, security basics, Pest tests and documentation.
---

# FancyCrud code rules

Follow these rules for every piece of code you generate or modify with `al-rahabi/fancy-crud`. If a rule and the user's
explicit instruction conflict, follow the user and say which rule you broke. Templates: [references/templates.md](references/templates.md).

## 1. Who does what

| Layer | Does | Never does |
|-------|------|------------|
| **FormRequest** | validates and normalizes *all* input (body, query string, route values) | business logic |
| **Controller** | authorizes, takes `validated()` data, calls repository/service, runs before/after logic, returns the response | queries, loops, business rules, building arrays by hand |
| **Service / Action** | the write logic, from plain typed arguments | touches the request, the authenticated user, `auth()`, `Gate`, policies, `session()` |
| **Repository** | read queries (index/show), filters, ordering, eager loading | writes, authorization |
| **Resource / Table** | shape the JSON | queries, lazy loading |
| **Policy** | answers "may this user do X to this model" | anything else |

## 2. Hard rules

1. **Authorization lives in the controller.** `$this->authorize('create', Post::class)`, `$this->authorize('update', $post)`,
   `$this->authorize($action->ability(), Model::class)`, always *before* calling the service. Never authorize inside a service,
   action, repository, job or bulk-action handler.
2. **Before/after logic lives in the controller.** Anything that happens around a write (extra checks, events, notifications,
   cache busting, jobs, audit logs) goes into the controller's hooks `beforeStore/afterStore`, `beforeUpdate/afterUpdate`,
   `beforeDestroy/afterDestroy` (or into the method itself when you override it). The service has no hooks. Pure attribute
   derivation from the data itself (`storeAdditionalData(array $data)`, e.g. a slug) is allowed in the service because it needs
   neither request nor user.
3. **Services and actions are request-free and user-free.** They receive everything as typed arguments. If the acting user matters
   (`created_by`), the controller puts the id into the data it passes, by overriding `storeData()` / `updateData()`:
   `[...parent::storeData(), 'created_by' => $this->user()?->getAuthIdentifier()]`.
   A service must be callable from a console command or a queued job with no HTTP context.
4. **No raw request values.** Never use `request()`, `$request->input()/get()/all()/query()/only()`, `$_GET`, `Request::` or
   `old()` for values that drive logic. Everything comes from a FormRequest's `validated()` — including index query
   parameters (`per_page`, `search`, `fields`, `order_by`, filters) and bulk payloads. The package does this for you:
   `BaseIndexRequest` and `BaseBulkRequest` validate them and the controller hands the validated values to the repository
   (`withParameters()`); the repository never reads the request. Declare each filter's rules with `Filter::rules()`, or subclass the
   request and set the controller's `$indexRequest` / `$bulkRequest` to add rules.
5. **Thin controllers.** A controller method is: authorize → validated data → one repository/service call → (after logic) → respond.
   Roughly ≤ 10 lines. If it grows, move the logic to a service/action, the query to the repository, the shaping to a resource.
6. **Short, testable methods.** One job per method, ≤ ~15 lines, explicit typed inputs and outputs, no hidden globals
   (`now()`/`auth()`/`request()` are inputs — pass them in), no side effects mixed with calculations. If you need a comment to
   explain a block, extract a method named after the comment.
7. **No N+1.** See section 3.
8. **Strong typing.** See section 4.
9. **Security.** See section 5.
10. **Tests.** See section 6.
11. **Documentation** is updated in the same change. See section 7.

## 3. N+1 prevention

- Every relation a resource, table column, accessor or policy reads must be eager loaded: `$indexRelation`, `$indexCountRelation`,
  `$showRelation`, `$showCountRelation` on the repository, or `Column::relation()` / `Column::count()` in a table.
- Resources read relations with `whenLoaded()` / `whenCounted()`; they never trigger a lazy load.
- No queries inside loops, resources, accessors or bulk handlers. Chunk with `chunkById()` and eager load with `with()` on the
  query (override `BaseRepository::bulkQuery()` or `globalQuery()` when a handler needs relations).
- Prefer `withCount()` / `withExists()` / `withSum()` over `->relation->count()`.
- Per-row checks run once per row: `Column::actions()` (policy per ability per row), policies calling model `canUpdate($user)` and
  `valueUsing()` closures must not lazy-load relations — eager load what they read. Table rows take the viewer explicitly
  (`$table->rows($models, $columns, $this->user())`); never call `auth()` inside a table, column or resource.
- Enable `Model::preventLazyLoading(! app()->isProduction())` in `AppServiceProvider::boot()`, and **always** in the test base case,
  so a missing `with()` fails the test instead of hiding.
- Add a test per index endpoint that proves the query count does not grow with the number of rows (see templates).

## 4. Typing

- `declare(strict_types=1);` in every new file.
- Type every parameter, return value and property (`protected string $model`, `: JsonResponse`, `: ?Model`, `: void`).
  `mixed` only when truly unavoidable (and then say why in a docblock).
- Describe arrays that have a shape: `@param array{title: string, body?: string|null} $data`, `@return list<Post>`,
  `@var array<int, class-string<BulkAction>>`. Prefer a readonly DTO / enum over a loose array when the shape is used in more than one place.
- Use enums for fixed sets (statuses, abilities), `readonly` for value objects, `final` for leaf classes that are not meant to be extended.
- No dynamic property access on models for known attributes without `@property` docblocks or casts; declare `$casts`.
- If the project has PHPStan/Larastan, new code must pass at the project's level.

## 5. Security

- **Mass assignment:** explicit `$fillable` on every model that receives request data. Never `protected $guarded = []` on models
  exposed to request payloads (the package's test fixtures use it only because they are test-only).
- **SQL injection:** never concatenate user input into `whereRaw()`, `orderByRaw()`, `selectRaw()`, `DB::raw()`. Use bindings
  (`whereRaw('length(title) > ?', [$n])`). Column names that come from the client (`order_by`, `fields`, filter keys) must be mapped
  through an allow-list — `orderingFields()`, table columns and filter objects already do this; never pass a client string to `orderBy()` directly.
- **Authorization:** every state-changing operation is authorized with a Policy in the controller
  (`$this->authorize('update', $order)`); bulk actions via `BulkAction::ability()`.
- **Livewire (if used):** `#[Locked]` on immutable properties, `$this->authorize()` in every state-changing action method, never keep
  unmasked sensitive models in public properties.
- **XSS (Blade):** `{{ $escaped }}`; `{!! $raw !!}` only for trusted internal HTML, never user content.
- **Secrets:** no `env()` outside `config/*.php`; read `config('services.x.key')`; run `config:cache` in production.

## 6. Testing with Pest

- **One test file per controller**: `tests/Feature/<Resource>Test.php` (e.g. `PostTest.php`).
- **Start with the generated suite**: `crudTest('posts')->model(Post::class)->all()->…->run();` covering every verb the controller exposes
  (use `only()`/`except()` for the rest). Then add feature tests **outside** the CRUD basics, grouped with `describe()`:
  custom endpoints, filters, table fields/ordering, validation edge cases, authorization edge cases.
- **After-logic has tests too**: for every event, notification, job, cache write or audit entry the controller runs after a write,
  assert it with `Event::fake()`, `Notification::fake()`, `Queue::fake()`, `Bus::fake()` — and assert it does *not* fire when the write fails.
- **Bulk actions are tested**: each action × each mode (`query`/`sync`/`job`), the three selection kinds (`ids`, `filters`, `all`),
  required/optional/invalid `extra`, authorization (403), unknown action (422), and the effect on the data.
- **Services/actions get plain unit tests** (no HTTP, no logged-in user) — this proves rules 1–3.
- **N+1 test** per index (query count equal for 1 and for 5 records) with lazy-loading prevention on.
- Write behaviour-driven Pest: `it('…')`, `expect()`, datasets for variants, no logic in assertions. Run with `composer test`.

## 7. Documentation

After **every** change update the docs in the right place, in the same change:

| You changed | Update |
|-------------|--------|
| a package group (`src/<Group>`) | `docs/<group>/README.md` |
| request flow / endpoint | `docs/flows.md` and `docs/http/README.md` |
| directory layout / new class | `docs/architecture.md` directory map |
| tests / fixtures | `docs/testing/README.md` |
| a rule or convention | `CLAUDE.md` and this skill |
| a host-app resource | the app's own docs for that module (request params, columns, bulk actions, permissions, after-write effects) |

If no doc exists for the group, create `docs/<group>/README.md` and link it from `docs/README.md` and `CLAUDE.md`.

## 8. Checklist before you finish

- [ ] Controller methods are thin; authorization first; before/after logic only in the controller
- [ ] Service/action have no `request()`, `auth()`, `Gate`, `authorize`, `$request`
- [ ] All input comes from FormRequests (including index/bulk params)
- [ ] Every relation used by resource/table is eager loaded; N+1 test present
- [ ] Types everywhere, `strict_types`, array shapes documented
- [ ] `$fillable` set; no raw SQL with user input
- [ ] Test file: `crudTest` first, then feature, after-logic and bulk tests; `composer test` is green
- [ ] Docs updated

## 9. What the base classes already give you

`BaseCrudService` / actions: no user, no authorization, no hooks. `CrudController`: authorizes first (`create` / `update` / `delete` /
bulk ability), validates through FormRequests, runs the controller hooks around exactly one service call. `BaseRepository`: reads only
the validated parameters it was given. Do not reintroduce `auth()`, `request()`, `Gate` or hooks in services, actions or repositories.
Model-level rules go in `canUpdate($user)` / `canDelete($user)` on the model (the policy calls them → 403), not in the service.

# CLAUDE.md — FancyCrud

Laravel **package** (`al-rahabi/fancy-crud`, namespace `FancyCrud\`, PHP ^8.3, Laravel 11–13): abstract building blocks for JSON CRUD APIs.
It has no routes/models/migrations; host apps extend the base classes. Full docs live in [`docs/`](docs/README.md) — read the relevant
group doc before changing code in that group.

## Commands
```bash
composer install
composer test      # Pest + Orchestra Testbench (sqlite :memory:), tests/Feature
composer format    # Pint
php artisan fancy-crud:permissions [--guard=web --guard=admin]   # in a host app
```

## Architecture in one screen
Controller (`Contracts/CrudController`) → **Repository** reads (`index`/`find`) and **Service** writes (`store/update/destroy` → `Actions/Crud/*`).
Responses use the `ResponseTrait` envelope `{data,status,message,code,meta}`. Resources shape JSON, Requests validate (per HTTP method),
Policies/Spatie permissions authorize with `"{action} {resource}"` strings, `BaseExcel` + `ExcelHeader` describe import/export sheets,
`crudTest()` generates the endpoint test-suite. Flow details: [docs/flows.md](docs/flows.md).

## Directory groups
| Group | Path | Doc |
|-------|------|-----|
| crud-core | `src/Services`, `src/Actions/Crud`, `src/Repositories` | [docs/crud-core](docs/crud-core/README.md) |
| http | `src/Contracts/{Controller,CrudController,BaseRequest,BaseResource}`, `src/Traits/{ResponseTrait,PaginationMetaTrait}`, `src/Middleware`, `resources/lang` | [docs/http](docs/http/README.md) |
| authorization | `src/Contracts/BasePolicy`, `src/Permissions`, `src/Console`, `config/fancy-crud.php` (`permissions`) | [docs/authorization](docs/authorization/README.md) |
| excel | `src/Excel/**` | [docs/excel](docs/excel/README.md) |
| testing | `src/Testing/**`, `tests/**` | [docs/testing](docs/testing/README.md) |
| table | `src/Table`, `src/Filters` | [docs/table](docs/table/README.md) |
| bulk | `src/Bulk` | [docs/bulk](docs/bulk/README.md) |
| skill | `resources/skills/fancy-crud` | [docs/skill](docs/skill/README.md) |
| support | `src/Support`, `src/Traits/{Activable,Sortable,HasSlug,Makeable}`, `src/Casts`, `src/helpers.php`, `Contracts/{BaseEnumTrait,BaseNotification}` | [docs/support](docs/support/README.md) |
| guides | adding a resource, extension points, behaviours to know | [docs/guides](docs/guides/README.md) |

Also: [docs/architecture.md](docs/architecture.md) (layers, provider, dependencies).

## Rules to follow when editing
- **Keep the split**: repositories read, services/actions write, controllers only orchestrate. No business logic in controllers.
- **Permission names** are `"{action} {resource}"` with resource = plural snake-case with spaces (`BlogPost` → `blog posts`). Keep
  `BaseCrudService::getPermissionName()` and `BasePolicy::$resource` derivations consistent.
- **Code rules for code that uses the package** (thin controllers, authorization + before/after logic in the controller, request-free/user-free
  services, validated input only, no N+1, strict typing, Pest test file per controller) live in
  [`resources/skills/fancy-crud/SKILL.md`](resources/skills/fancy-crud/SKILL.md) — follow them when writing fixtures/examples too.
- **Update the docs in the same change** as the code (see the mapping table in the skill, section 7).
- Prefer adding overridable hooks to changing signatures; everything is meant to be subclassed.
- Excel code stays HTTP-free (`BaseExcel`, managers); HTTP bits live in `ExportTrait`/`ImportTrait`.
- Global helpers must be wrapped in `function_exists` (host apps override them). New autoloaded files go in `composer.json` `autoload.files`.
- `spatie/laravel-permission` and `laravel-notification-channels/fcm` are **required** dependencies.
- Permission names come only from `Support/PermissionName`. `PermissionSyncer` deletes undefined permissions of the guards it syncs and gives each guard a main role with all its permissions.
- New behaviour needs a test; the quickest is extending `tests/Fixtures/*` and `tests/Feature/PostCrudTest.php` (`crudTest()`).
  Suites: `tests/Feature` (`crudTest` on fixtures) and `tests/Unit` (Spatie + support classes); see docs/testing for the count.
- Services, actions, repositories and jobs never read the request or the user and never authorize; the controller authorizes (before validation), resolves the user
  (`Controller::user()`), validates through FormRequests (`BaseIndexRequest`, `BaseBulkRequest`, store/update requests) and runs before/after logic
  (`beforeStore/afterStore/…` hooks). Keep routes behind auth middleware. See [docs/guides](docs/guides/README.md).

## Where to look
- A new endpoint verb or response shape → `Contracts/CrudController.php`, `Traits/ResponseTrait.php`.
- Filtering/search/ordering → `Repositories/BaseRepository.php` (`applyFilters/applySearch/applyOrder`); filters are `Filters/BaseFilters` + `Filters/Filter` classes.
- Column-driven index (`fields`, column filters/ordering) → `Table/BaseTable.php`, `Table/Column.php`; bulk endpoint → `Bulk/*`, `CrudController::bulk()`.
- Write logic → `Services/BaseCrudService.php`, `Actions/Crud/*`; authorization and before/after hooks → `Contracts/CrudController.php`.
- Spreadsheet behaviour → `Excel/BaseExcel.php`, `Excel/ExcelHeader.php`, `Excel/Imports/ResourceImport.php`.
- Multi-guard permissions → `Permissions/PermissionSyncer.php`, config `fancy-crud.permissions.guards`.
- Reference implementation of every piece → `tests/Fixtures/` (`Post*`).

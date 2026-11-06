# FancyCrud — documentation index

FancyCrud (`fancycrud/fancy-crud`, namespace `FancyCrud\`) is a Laravel **library of abstract building blocks** for
JSON CRUD APIs: controller → service → action → repository, plus policies, form requests, API resources, Excel
import/export, a declarative Pest test generator, and Spatie-permission syncing.
It ships no routes, models or migrations: the host app extends the base classes.

Written for AI agents and new contributors. Read in this order:

| # | Doc | What it explains |
|---|-----|------------------|
| 1 | [architecture.md](architecture.md) | Layers, directory map, dependencies, design rules |
| 2 | [flows.md](flows.md) | Step-by-step request flows (index, show, store, update, destroy, export, import, auth, tests) |
| 3 | [crud-core/](crud-core/README.md) | `Services`, `Actions`, `Repositories` — the write and read halves |
| 4 | [http/](http/README.md) | `Contracts` controllers / requests / resources, `ResponseTrait`, middleware, translations |
| 5 | [authorization/](authorization/README.md) | `BasePolicy`, permission naming, Spatie `PermissionSyncer`, multi-guard config |
| 6 | [excel/](excel/README.md) | `BaseExcel`, `ExcelHeader`, export/import managers and result object |
| 7 | [testing/](testing/README.md) | `crudTest()` builder, `InteractsWithCrud`, `InteractsWithPermissions`, package's own tests |
| 8 | [support/](support/README.md) | Traits, casts, enums, helpers, notification, config |
| 9 | [table/](table/README.md) | `BaseTable` + `Column`: column-driven index built from the requested `fields` |
| 10 | [bulk/](bulk/README.md) | `BulkAction` (query / sync / job), `BulkRunner`, `POST {resource}/bulk` |
| 11 | [guides/](guides/README.md) | How to add a resource end to end, extension points, behaviours worth knowing |

Source of truth is always the code under `src/`; these docs describe it as of the current commit.

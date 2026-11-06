# skill — AI code rules (`resources/skills/fancy-crud`)

A Claude Code **skill** that tells an AI how to write code with this package. It is the single source of truth for the code
rules; this page explains how to install it and what it enforces.

| File | Purpose |
|------|---------|
| `resources/skills/fancy-crud/SKILL.md` | the rules (frontmatter `name: fancy-crud`), loaded by Claude Code when a task touches FancyCrud classes |
| `resources/skills/fancy-crud/references/templates.md` | typed templates: FormRequest, service, controller, Pest test file |

## Installing

**Publish** (copies into the host project, commit it so the whole team gets it):
```bash
php artisan vendor:publish --tag=fancy-crud-skills    # → .claude/skills/fancy-crud/
```

**Use directly** without publishing: copy or symlink the folder
```bash
cp -r vendor/fancycrud/fancy-crud/resources/skills/fancy-crud .claude/skills/        # project only
cp -r vendor/fancycrud/fancy-crud/resources/skills/fancy-crud ~/.claude/skills/      # all projects
```
Re-publish (`--force`) after upgrading the package to pick up rule changes.

## What it enforces

1. **Layering** — FormRequest validates, controller orchestrates, service/action write, repository reads, resource/table shape JSON.
2. **Authorization in the controller** (policies), before the service call; never in services/actions/jobs.
3. **Before/after logic in the controller** around one service call; the `before*/after*` hooks are not used.
4. **Services and actions** take typed arguments only — no request, no authenticated user (the actor travels as data).
5. **No raw request values** — everything via FormRequests, index/bulk parameters included.
6. **Thin controllers, short testable methods.**
7. **No N+1** — declared eager loads, `whenLoaded`, `chunkById` + `with`, lazy-loading prevention, a query-count test per index.
8. **Strong typing** — `strict_types`, full signatures, array shapes, enums/DTOs.
9. **Security** — explicit `$fillable`, no raw SQL with user input, policies on every write, Livewire/XSS/secrets rules.
10. **Pest tests** — one file per controller starting with `crudTest()`, then feature, after-logic and bulk-action tests.
11. **Documentation updated with every change**, in the right place (mapping table in the skill).

## Rules and base classes agree
The base classes implement the rules, so generated code and the package pull the same way: services/actions take no user, no request and
do no authorization and have no hooks; `CrudController` authorizes first and offers the `before*/after*` controller hooks plus
`storeData()/updateData()`; `BaseIndexRequest` / `BaseBulkRequest` validate index and bulk input and the repository only reads the
validated parameters (`withParameters()`). The skill tells the AI to use those seams instead of re-adding `auth()`, `request()` or hooks.

## Maintaining
Change the rules in `SKILL.md` only (never keep a second copy); `tests/Unit/SkillTest.php` checks the frontmatter, the reference links
and the `fancy-crud-skills` publish tag. When a package rule in `CLAUDE.md` changes, mirror it in the skill.

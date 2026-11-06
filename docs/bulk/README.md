# bulk — actions over many records (`src/Bulk`)

`POST {resource}/bulk` applies one *bulk action* to a selection of records. Actions are classes registered on the repository;
they are independent of the [table](../table/README.md) (but the table's index `meta` lists them for the client).

| Class | Role |
|-------|------|
| `Bulk\BulkAction` | abstract action: `name()`, `label()`, `ability()`, `rules()` (extra data), `mode()`, `describe()` |
| `Bulk\HandlesByQuery` | one statement over the whole selection — `byQuery(Builder $query, array $extra): ?int` |
| `Bulk\HandlesBySync` | immediate, per record — `bySync(Model $model, array $extra): void` |
| `Bulk\HandlesByJob` | queued, per record — `byJob(Model $model, array $extra): void` |
| `Bulk\BulkRunner` | turns validated data into action + selection + extra, runs / dispatches the action (HTTP-free, reads no request) |
| `Contracts\BaseBulkRequest` | the FormRequest that validates the payload (replaceable with the controller's `$bulkRequest`) |
| `Bulk\BulkActionJob` | the queued job (`ShouldQueue`) used for `HandlesByJob` |

## Defining an action

The way an action runs is decided by **which interface it implements**:

```php
class PublishBulkAction extends BulkAction implements HandlesByQuery   // name(): "publish"
{
    public function rules(): array                                      // extra data: key => Laravel rules
    {
        return ['status' => ['required', 'in:published,archived'], 'note' => 'nullable|string'];
    }

    public function byQuery(Builder $query, array $extra): ?int
    {
        return $query->update(['status' => $extra['status']]);          // returned int = "processed"
    }
}

class DeleteBulkAction extends BulkAction implements HandlesBySync
{
    public function ability(): string { return 'delete'; }
    public function bySync(Model $model, array $extra): void { $model->delete(); }
}

class ArchiveBulkAction extends BulkAction implements HandlesByJob
{
    public function chunkSize(): int { return 500; }
    public function queue(): ?string { return 'bulk'; }
    public function byJob(Model $model, array $extra): void { $model->update(['status' => 'archived']); }
}
```

Register on the repository (and add the route):

```php
class PostRepository extends BaseRepository
{
    protected array $bulkActions = [PublishBulkAction::class, DeleteBulkAction::class, ArchiveBulkAction::class];
}
// routes — before apiResource
Route::post('posts/bulk', [PostController::class, 'bulk']);
```

| Mode | Runs | Model events | Response `data` |
|------|------|--------------|-----------------|
| `query` | in the request, one statement | no (mass update/delete) | `{mode:"query", queued:false, processed:N}` (N = returned int) |
| `sync` | in the request, `chunkById(chunkSize())`, handler per model | yes | `{mode:"sync", queued:false, processed:N}` |
| `job` | `BulkActionJob` on `connection()`/`queue()`; same chunked loop inside the job | yes | `{mode:"job", queued:true, processed:null}` |

If an action implements several interfaces the preference is query → job → sync; override `mode()` to change it. An action
implementing none throws a `LogicException`. Overridable: `name()` (default: class name without `BulkAction`, snake-case),
`label()`, `ability()` (default `update`), `chunkSize()` (200), `connection()`, `queue()`.

The job carries only class names and arrays (repository class, action class, selection, extra), so repositories and actions
need a constructor without arguments. There is **no request and no authenticated user** inside the job.

## The request

`POST /posts/bulk` (JSON):

```json
{ "action": "publish", "ids": [1, 2, 3], "extra": { "status": "published" } }
{ "action": "publish", "filters": { "status": "draft", "is_active": 1 }, "extra": { "status": "archived" } }
{ "action": "delete",  "all": true }
```

| Key | Rule |
|-----|------|
| `action` | required; one of the repository's action names |
| `ids` \| `filters` \| `all` | **exactly one**. `ids`: non-empty array · `filters`: key/value pairs of *declared* filters (repository, table, `forced_items`), each checked with its `Filter::rules()`, with at least one usable value · `all`: `true` |
| `extra` | validated with the action's `rules()` (`extra.<key>`); mark `required` for mandatory data; only keys with rules reach the action |

Validation lives in `BaseBulkRequest` (`FancyCrud\Contracts`); the controller only calls `app($bulkRequest)->validated()` and `BulkRunner::resolve()`.
Errors: 422 with Laravel's validation errors (`action`, `selection`, `filters`, `extra.<key>`); an empty or unknown-only `filters` is
rejected rather than read as "everything". 403 when the user lacks the action's `ability()` on the model (policy → `"{ability} {resource}"`
permission). 404 when the repository declares no bulk actions. Authorization is class-level (`authorize($ability, $model)`); per-record
checks belong in the handler.

## How the selection becomes a query

`BaseRepository::bulkQuery(array $selection)` — `ids` ⇒ `whereKey()`, `filters` ⇒ `resolvedFilters()->apply()` (same filter objects as
the index; values come from the payload, not the request), `all` ⇒ `globalQuery()`. Override `globalQuery()` to scope bulk work (tenant,
soft-deletes…). The index `meta.table.bulk_actions` lists each action's `name`, `label`, `mode` and `extra` (`required` + string rules) so a UI can render forms.

## Tests
`tests/Feature/BulkActionTest.php` — fixtures `PublishBulkAction` (query), `DeleteBulkAction` (sync), `ArchiveBulkAction` (job) on
`PostTableRepository`, route `POST api/table-posts/bulk`. Job tests use `Queue::fake()`; the default test queue is `sync` so the job also runs end to end.

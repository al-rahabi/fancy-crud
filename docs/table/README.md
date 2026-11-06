# table — column-driven index (`src/Table`)

A **table** describes the index of a resource as a list of columns. When a repository declares one, `index` stops using the
Resource and eager-load arrays and instead builds the query **from the columns the client asked for** — nothing more is
selected, loaded or counted. Filters and ordering are declared on the same columns.

| Class | Role |
|-------|------|
| `Table\BaseTable` | abstract definition: `columns()`, default columns, extra filters, model. Also builds the query and the rows. |
| `Table\Column` | one column: type, label, select/relation/count, getter, ordering, filters. |
| `Filters\Filter` / `BaseFilters` | the filter objects columns attach (see [crud-core](../crud-core/README.md#filters)). |

## Enabling it

```php
class PostTable extends BaseTable
{
    protected ?string $model = Post::class;          // optional: used when the repository declares no $model

    public function columns(): array
    {
        return [
            Column::link('title')->orderable()->filterable('LIKE')->url(fn (Model $row) => route('posts.show', $row)),
            Column::text('status')->orderable()->filterable(),
            Column::switch('is_active')->filterable(),
            Column::text('author_name')->label('Author')->relation('author', 'name')->filterable()
                ->orderable(fn (Builder $q, string $dir) => $q->orderBy(Author::select('name')->whereColumn('authors.id', 'posts.author_id'), $dir)),
            Column::text('comments_count')->count('comments')->orderable(),
            Column::image('cover')->optional()->disk('public'),
            Column::text('body')->optional(),
            Column::actions(),                       // show / update / delete abilities per row
        ];
    }

    public function filters(): array                 // filters not tied to a column
    {
        return [Filter::make('author')->column('author_id')];
    }
}

class PostRepository extends BaseRepository
{
    protected ?string $table = PostTable::class;     // that's all
}
```

No controller change is needed: `CrudController::index()` checks `$repository->table()`.
Without a table nothing changes (Resource path, `$indexRelation`, `$indexCountRelation`). With a table, `$indexRelation` /
`$indexCountRelation` and the Resource are **not** used by `index` (show is unaffected).

## The request protocol

1. **First request** — `GET /posts`: no `fields` ⇒ the *default columns* are returned.
2. **After that** — the client picks what it needs: `GET /posts?fields[]=title&fields[]=author_name` or `?fields=title,author_name`.
   Order follows the request; unknown or duplicate names are ignored; if none is valid the defaults are used.
3. Filters (`?status=published&author_name=Ann`), ordering (`?order_by[0][name]=title&order_by[0][direction]=desc`), `?search=`,
   `?per_page=`, `?unlimited=1` and `?forced_items[]=` work as for any index.

Response:

```json
{
  "data": [
    { "id": 1, "title": {"label": "Alpha", "url": "/posts/1"}, "author_name": "Bob", "is_active": true,
      "actions": {"show": true, "update": true, "delete": false} }
  ],
  "meta": {
    "current_page": 1, "total": 3, "per_page": 10, "last_page": 1, "pages_count": 1,
    "table": {
      "selected": ["title", "author_name", "is_active", "actions"],
      "columns": [
        {"name": "title", "label": "Title", "type": "link", "default": true, "orderable": true, "filterable": ["title"], "meta": []}
      ],
      "bulk_actions": [ {"name": "publish", "label": "Publish", "mode": "query", "extra": {"status": {"required": true, "rules": ["required","in:published,archived"]}}} ]
    }
  }
}
```
`data[*]` always holds the primary key (`id`) plus one entry per selected column. `meta.table.columns` lists **all** columns
(so the client can build a column picker); `selected` are the ones in `data`; `bulk_actions` describes the repository's
[bulk actions](../bulk/README.md).

## Query building (`BaseTable::prepare`)

For the selected columns only:
- `select()` = primary key + the database columns each column declares + relation keys. `*` is used instead when a column
  has a custom getter that does not declare what it reads (see below).
- `with()` = relations of relation columns. The foreign key needed to load the relation (`BelongsTo` FK, `MorphTo` FK + type,
  `HasOne/HasMany` local key) is selected automatically. Nested relations (`'author.company'`) load nested; only the first
  segment's key is added.
- `withCount()` = counts of count columns.

Filters, ordering and search are applied afterwards and are independent of which columns are returned (you can filter or order by a column that is not requested).

## `Column`

Factories: `Column::text|image|link|switch($name)`, `Column::actions(array $abilities = ['show','update','delete'], $name = 'actions')`,
`Column::make($name, $type)`. `$name` is the key in the response, the value of `fields`, and (by default) the database column.

| Method | Effect |
|--------|--------|
| `label($s)` | display label (default: `Str::headline(name)`) |
| `default(bool)` / `optional()` | in / out of the default set |
| `select($cols)` | database columns this column needs (use with accessors / getters) |
| `relation($rel, $column = null)` | eager-load `$rel`, value = `$rel.$column` (or the related model itself when no column), FK selected for you |
| `from($path)` | read `data_get($row, $path)` — e.g. a JSON attribute `meta.color`; first segment is selected unless `select()` is given |
| `count($rel)` | `withCount($rel)`; name the column `{rel}_count` |
| `valueUsing(fn (Model $row) => …)` | custom value. Declare `select()`/`relation()` for what it reads, otherwise every column (`*`) is selected |
| `disk($d)` | *image*: turn a stored path into `Storage::disk($d)->url()` (absolute URLs are left alone) |
| `url(fn (Model $row) => …)` | *link*: value becomes `{label, url}`; without it `{label: value, url: value}` |
| `meta([...])` | free data sent in `describe()` |
| `orderable(true\|'col'\|Closure)` | see below |
| `filterable($condition = '=', $method = 'where')` / `filter(Filter ...)` | see below |

Values by type: **text** raw value · **image** URL · **link** `{label,url}` (null stays null) · **switch** `bool`/null ·
**actions** `{ability: Gate::allows(ability, row)}` (needs no database column).

### Ordering
Not orderable by default (requests for it are ignored — `order_by` only honours declared fields).
- `orderable()` — order by the column itself (`posts.title`; the bare name for counts and unknown-select columns).
- `orderable('posts.created_at')` — order by another database column.
- `orderable(fn (Builder $q, string $direction) => …)` — custom ordering query (joins, subqueries, relations).

Table ordering is merged with the repository's `orderingFields()`; the repository wins on a name clash. No `order_by` ⇒ `defaultOrdering()`.

### Filters
- `filterable()` adds `Filter::make($name)` bound to the column: on the real database column, or through `whereHas` for relation columns.
  `filterable('LIKE')`, `filterable('=', 'whereDate')` change operator/method.
- `filter(Filter::make(...)->…)` attaches any hand-built filter.
- `BaseTable::filters()` holds filters without a column.
- `Filter::column('db_col')` maps a request key to a different database column.

The effective set is `BaseRepository::resolvedFilters()`: table filters + repository `filters()` (+ built-in `forced_items`);
on a name clash the repository's filter wins. Only declared names are read from the request.

## Table-level API (`BaseTable`)

`columns()` (abstract) · `model()` · `defaultColumns(): string[]` (override to pick defaults by name; default = non-`optional()` columns) ·
`filters()` · `allFilters()` · `columnMap()` · `selected(?array $requested): Column[]` · `prepare(Builder, Column[])` ·
`orderingFields(Model)` · `row()/rows()` · `describe(Column[])`.

Repository seams: `table()`, `requestedFields()`, `resolvedFilters()`, `getModel()` (falls back to the table's model).

## Gotchas
- Accessor / computed columns need `select([...])` (or `relation`) — a bare `Column::text('full_name')` selects a database column called `full_name`.
- A relation column with `orderable()` (true) can't be ordered by itself; give a closure.
- The table replaces the Resource on `index`; show/store/update still use `$resource`.
- Columns are not permission-gated; hide sensitive fields by not declaring them (or build the table dynamically by overriding `table()`).

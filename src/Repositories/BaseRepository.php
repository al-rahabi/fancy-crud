<?php

namespace FancyCrud\Repositories;

use FancyCrud\Bulk\BulkAction;
use FancyCrud\Contracts\BaseIndexRequest;
use FancyCrud\Filters\BaseFilters;
use FancyCrud\Filters\FilterSet;
use FancyCrud\Table\BaseTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Owns everything about building the index and show queries for a resource:
 * the model, eager-loaded relations, pagination, search, filtering and
 * ordering. Concrete repositories only override configuration and query hooks.
 */
abstract class BaseRepository
{
    protected string $model = Model::class;

    protected bool $paginated = true;

    protected int $limit = 10;

    /**
     * Upper bound for `?per_page=`; larger values are clamped to it.
     */
    protected int $maxLimit = 100;

    /**
     * Whether `?unlimited=1` may skip pagination. Off by default so a client
     * cannot dump a whole table; enable it only for small, bounded resources.
     */
    protected bool $allowUnlimited = false;

    protected array $indexRelation = [];

    protected array $indexCountRelation = [];

    protected array $showRelation = [];

    protected array $showCountRelation = [];

    /**
     * @var class-string<BaseFilters>|null
     */
    protected ?string $filters = null;

    /**
     * @var class-string<BaseTable>|null
     */
    protected ?string $table = null;

    /**
     * @var array<int, class-string<BulkAction>>
     */
    protected array $bulkActions = [];

    private ?BaseTable $tableInstance = null;

    /** @var array<string, mixed> */
    private array $parameters = [];

    /**
     * The model: `$model`, or the table's when only the table declares one.
     */
    public function getModel(): string
    {
        if ($this->model === Model::class && $this->table()?->model()) {
            return $this->table()->model();
        }

        return $this->model;
    }

    /**
     * The table definition of the index, if the resource has one. With a table,
     * the index query is built from the requested `fields` only.
     */
    public function table(): ?BaseTable
    {
        if ($this->table === null) {
            return null;
        }

        return $this->tableInstance ??= new $this->table;
    }

    /**
     * Bulk actions by request name.
     *
     * @return array<string, BulkAction>
     */
    public function bulkActions(): array
    {
        $actions = [];
        foreach ($this->bulkActions as $class) {
            $action = new $class;
            $actions[$action->name()] = $action;
        }

        return $actions;
    }

    /**
     * The query a bulk action runs over: `['ids' => […]]`, `['filters' => […]]` or `['all' => true]`.
     */
    public function bulkQuery(array $selection): Builder
    {
        $query = $this->globalQuery();

        if (isset($selection['ids'])) {
            return $query->whereKey($selection['ids']);
        }

        if (isset($selection['filters'])) {
            return $this->resolvedFilters()->apply($query, $selection['filters']);
        }

        return $query;
    }

    /**
     * Relations eager-loaded for the index query.
     */
    public function getRelations(): array
    {
        return $this->indexRelation;
    }

    /**
     * Count relations for the index query.
     */
    public function getCountRelations(): array
    {
        return $this->indexCountRelation;
    }

    /**
     * Relations eager-loaded for the show query.
     */
    public function getShowRelations(): array
    {
        return array_merge($this->showRelation, $this->getRelations());
    }

    /**
     * Count relations for the show query.
     */
    public function getShowCountRelations(): array
    {
        return $this->showCountRelation;
    }

    /**
     * Pagination limit, overridable per request via `per_page`, clamped to
     * `1..$maxLimit`.
     */
    public function getLimit(): int
    {
        $limit = (int) ($this->parameters['per_page'] ?? $this->limit);

        return max(1, min($limit, $this->maxLimit));
    }

    /**
     * The validated index parameters (`per_page`, `unlimited`, `search`, `fields`,
     * `order_by` and the filter values) this repository reads. The repository never
     * touches the request itself — the controller passes what the index request validated.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function withParameters(array $parameters): static
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * Validation rules of the repository's own parameters: one entry per filter
     * (from its `Filter::rules()`). Merged into the index request by {@see BaseIndexRequest}.
     *
     * @return array<string, array<int, mixed>>
     */
    public function indexRules(): array
    {
        $rules = [];
        foreach ($this->resolvedFilters()->all() as $filter) {
            $rules[$filter->name()] = $filter->getRules();
        }

        return $rules;
    }

    /**
     * Determine if the index results are paginated.
     */
    public function isPaginated(): bool
    {
        return $this->paginated;
    }

    /**
     * `?unlimited=1`, honoured only when `$allowUnlimited` is true.
     */
    public function forceUnlimited(): bool
    {
        return $this->allowUnlimited && filter_var($this->parameters['unlimited'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Start a fresh model query.
     */
    public function globalQuery(): Builder
    {
        return $this->getModel()::query();
    }

    /**
     * Customize the show query.
     */
    public function customShowQuery(Builder $globalQuery): Builder
    {
        return $globalQuery;
    }

    /**
     * Build and run the index query: relations, filters, search, ordering and
     * pagination.
     */
    public function index(): Collection|LengthAwarePaginator
    {
        $query = $this->globalQuery();

        if ($table = $this->table()) {
            $table->prepare($query, $table->selected($this->requestedFields()));
        } else {
            $query->with($this->getRelations())->withCount($this->getCountRelations());
        }

        $query = $this->applyFilters($query);
        $query = $this->applySearch($query);
        $query = $this->applyOrder($query);

        if ($this->isPaginated() && ! $this->forceUnlimited()) {
            return $query->paginate($this->getLimit());
        }

        return $query->get();
    }

    /**
     * Resolve a single record for the show query, with its show relations.
     */
    public function find(int|string|array|null $target): ?Model
    {
        return $this->customShowQuery($this->globalQuery())
            ->with($this->getShowRelations())
            ->withCount($this->getShowCountRelations())
            ->find($target);
    }

    /**
     * Searchable fields on the model.
     */
    public function searchableArray(): array
    {
        return (new ($this->getModel()))->getFillable();
    }

    /**
     * Columns the client asked for (`?fields[]=a&fields[]=b` or `?fields=a,b`).
     * Only meaningful when the repository has a table.
     *
     * @return array<int, string>
     */
    public function requestedFields(): array
    {
        $fields = $this->parameters['fields'] ?? null;
        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }

        return is_array($fields)
            ? array_values(array_filter(array_map('trim', array_filter($fields, 'is_string')), 'strlen'))
            : [];
    }

    public function getSearchTerm(): ?string
    {
        $term = $this->parameters['search'] ?? null;

        return is_string($term) ? $term : null;
    }

    /**
     * Apply default ordering when the request does not specify any.
     */
    public function defaultOrdering(Builder $query): Builder
    {
        return $query->orderByDesc('id');
    }

    /**
     * Fields available for ordering.
     */
    public function orderingFields(): array
    {
        return [];
    }

    public function requestedOrdering(): ?array
    {
        $order = $this->parameters['order_by'] ?? null;

        return is_array($order) ? $order : null;
    }

    /**
     * The filters of this resource: an instance of the `$filters` class, or
     * just the built-in defaults (`forced_items`) when none is set.
     */
    public function filters(): BaseFilters
    {
        return $this->filters ? new $this->filters : new class extends BaseFilters {};
    }

    /**
     * The repository's filters merged with the table's column filters (the
     * repository's win on a name clash). This is the set requests are read against.
     */
    public function resolvedFilters(): BaseFilters
    {
        $filters = $this->filters();
        $table = $this->table();

        if ($table === null) {
            return $filters;
        }

        return (new FilterSet($table->allFilters()))->with(...array_values($filters->all()));
    }

    public function requestedFilters(): array
    {
        return Arr::only($this->parameters, $this->resolvedFilters()->names());
    }

    protected function applySearch(Builder $query): Builder
    {
        $keyword = $this->getSearchTerm();
        if (is_null($keyword)) {
            return $query;
        }

        $model = new ($this->getModel());
        $table = $model->getTable();
        // Everything stays in one group so the ORs never escape the filters and
        // scopes already on the query: `… AND (a LIKE ? OR b LIKE ? OR id = ?)`.
        $query->where(function (Builder $subQuery) use ($keyword, $table, $model): void {
            foreach ($this->searchableArray() as $fieldName) {
                if (str_contains($fieldName, '.')) {
                    [$relation, $field] = explode('.', $fieldName);
                    /**
                     * Bare $field, not "$table.$field" — $qq is already
                     * scoped to the related model's own query inside
                     * orWhereRelation()'s whereHas() subquery, so prefixing
                     * it with the OUTER model's table name would reference a
                     * column that doesn't exist there.
                     */
                    $subQuery->orWhereRelation($relation, fn (Builder $qq) => $qq->where($field, 'LIKE', "%{$keyword}%"));
                } else {
                    $subQuery->orWhere("$table.$fieldName", 'LIKE', "%{$keyword}%");
                }
            }

            if ($model->getKeyType() !== 'int' || ctype_digit($keyword)) {
                $subQuery->orWhere($model->getQualifiedKeyName(), $keyword);
            }
        });

        return $query;
    }

    protected function applyFilters(Builder $query): Builder
    {
        return $this->resolvedFilters()->apply($query, $this->requestedFilters());
    }

    protected function applyOrder(Builder $query): Builder
    {
        $orderBy = $this->requestedOrdering();
        if (! $orderBy) {
            return $this->defaultOrdering($query);
        }

        $orderFields = array_merge($this->table()?->orderingFields($query->getModel()) ?? [], $this->orderingFields());
        foreach ($orderBy as $field) {
            $direction = $field['direction'] ?? 'asc';
            if (isset($orderFields[$field['name']])) {
                $orderField = $orderFields[$field['name']];
                $query = match (true) {
                    is_string($orderField) => $query->orderBy($orderField, $direction),
                    is_callable($orderField) => call_user_func($orderField, $query, $direction),
                    is_array($orderField) => $query->orderBy($orderField['field'], $orderField['direction'] ?? $direction),
                };
            }
        }

        return $query;
    }
}

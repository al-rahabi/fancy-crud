<?php

namespace FancyCrud\Repositories;

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

    protected array $indexRelation = [];

    protected array $indexCountRelation = [];

    protected array $showRelation = [];

    protected array $showCountRelation = [];

    public function getModel(): string
    {
        return $this->model;
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
     * Pagination limit, overridable per request via `per_page`.
     */
    public function getLimit(): int
    {
        return request('per_page', $this->limit);
    }

    /**
     * Determine if the index results are paginated.
     */
    public function isPaginated(): bool
    {
        return $this->paginated;
    }

    public function forceUnlimited(): ?bool
    {
        return request('unlimited');
    }

    /**
     * Start a fresh model query.
     */
    public function globalQuery(): Builder
    {
        return $this->model::query();
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
        $query = $this->globalQuery()
            ->with($this->getRelations())
            ->withCount($this->getCountRelations());

        $query = $this->applyFilters($query);
        $query = $this->applySearch($query);
        $query = $this->applyOrder($query);

        if ($this->isPaginated() && !$this->forceUnlimited()) {
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
        return (new $this->model)->getFillable();
    }

    public function getSearchTerm(): ?string
    {
        return request('search');
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
        return request()->input('order_by');
    }

    /**
     * Fields available for filtering.
     */
    public function filterFields(): array
    {
        return [];
    }

    public function requestedFilters(): array
    {
        $keys = collect($this->defaultFilters())->pluck('name')->toArray();
        $keys = array_merge($keys, collect($this->filterFields())->pluck('name')->toArray());

        return request()->only($keys);
    }

    /**
     * Filters always applied to the index query.
     */
    public function defaultFilters(): array
    {
        return [
            ['name' => 'forced_items', 'query' => fn(Builder $q, mixed $val) => $q->whereIn('id', Arr::wrap($val))],
        ];
    }

    protected function applySearch(Builder $query): Builder
    {
        $keyword = $this->getSearchTerm();
        if (is_null($keyword)) {
            return $query;
        }

        $table = (new $this->model)->getTable();
        $query->where(function (Builder $subQuery) use ($keyword, $table): void {
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
                    $subQuery->orWhereRelation($relation, fn(Builder $qq) => $qq->where($field, 'LIKE', "%{$keyword}%"));
                } else {
                    $subQuery->orWhere("$table.$fieldName", 'LIKE', "%{$keyword}%");
                }
            }
        });
        $query->orWhere("$table.id", $keyword);

        return $query;
    }

    protected function applyFilters(Builder $query): Builder
    {
        $defaultFilters = $this->defaultFilters();
        $defaultKeys = array_column($defaultFilters, 'name');

        $filteredKeys = array_filter($this->filterFields(), fn($item) => !in_array($item['name'], $defaultKeys));

        $filterFields = array_merge(array_values($filteredKeys), $defaultFilters);

        $requestedFilters = $this->requestedFilters();

        foreach ($filterFields as $filter) {
            $field = $filter['name'];
            $value = $filter['value'] ?? $requestedFilters[$filter['name']] ?? null;
            if (is_null($value)) {
                continue;
            }

            $condition = $filter['condition'] ?? '=';
            $method = $filter['method'] ?? 'where';

            if (isset($filter['relation'])) {
                $query->whereHas($filter['relation'], fn(Builder $subQuery) => $subQuery->$method($field, $condition, $value));
            } elseif (isset($filter['query']) && is_callable($filter['query'])) {
                $query->where(fn(Builder $sub) => call_user_func($filter['query'], $sub, $value));
            } else {
                $query->{$method}($field, $condition, $value);
            }
        }

        return $query;
    }

    protected function applyOrder(Builder $query): Builder
    {
        $orderBy = $this->requestedOrdering();
        if (!$orderBy) {
            return $this->defaultOrdering($query);
        }

        $orderFields = $this->orderingFields();
        foreach ($orderBy as $field) {
            $direction = $field['direction'] ?? 'asc';
            if (isset($orderFields[$field['name']])) {
                $orderField = $orderFields[$field['name']];
                $query = match (true) {
                    is_callable($orderField) => call_user_func($orderField, $query, $direction),
                    is_string($orderField) => $query->orderBy($orderField, $direction),
                    is_array($orderField) => $query->orderBy($orderField['field'], $orderField['direction'] ?? $direction),
                };
            }
        }

        return $query;
    }
}

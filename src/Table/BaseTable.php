<?php

namespace FancyCrud\Table;

use FancyCrud\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * Describes the index of a resource as a table: the available columns, which of
 * them are returned by default, and the filters that come with them. When a
 * repository declares a table (`$table = PostTable::class`), `index` builds its
 * query from the requested `fields` only.
 *
 * ```php
 * class PostTable extends BaseTable
 * {
 *     protected ?string $model = Post::class;
 *
 *     public function columns(): array
 *     {
 *         return [
 *             Column::text('title')->orderable()->filterable('LIKE'),
 *             Column::text('author_name')->relation('author', 'name'),
 *             Column::switch('is_active')->filterable(),
 *             Column::actions(),
 *         ];
 *     }
 * }
 * ```
 */
abstract class BaseTable
{
    /** Model used when the repository does not declare one. */
    protected ?string $model = null;

    /** @var array<string, Column>|null */
    private ?array $map = null;

    /**
     * @return array<int, Column>
     */
    abstract public function columns(): array;

    public function model(): ?string
    {
        return $this->model;
    }

    /**
     * Names returned when the request carries no (valid) `fields`. Defaults to
     * every column that is not marked `optional()`.
     *
     * @return array<int, string>
     */
    public function defaultColumns(): array
    {
        return array_keys(array_filter($this->columnMap(), fn (Column $column) => $column->isDefault()));
    }

    /**
     * Filters that are not tied to a column.
     *
     * @return array<int, Filter>
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * Column filters first, then the table's own.
     *
     * @return array<int, Filter>
     */
    public function allFilters(): array
    {
        $filters = [];
        foreach ($this->columnMap() as $column) {
            array_push($filters, ...$column->filters());
        }

        return [...$filters, ...$this->filters()];
    }

    /**
     * @return array<string, Column>
     */
    public function columnMap(): array
    {
        if ($this->map === null) {
            $this->map = [];
            foreach ($this->columns() as $column) {
                $this->map[$column->name()] = $column;
            }
        }

        return $this->map;
    }

    /**
     * The columns to return: the requested ones in the requested order (unknown
     * names are ignored), or the defaults when none is valid.
     *
     * @param  array<int, string>|null  $requested
     * @return array<int, Column>
     */
    public function selected(?array $requested = null): array
    {
        $map = $this->columnMap();
        $selected = [];

        foreach ($requested ?? [] as $name) {
            if (is_string($name) && isset($map[$name])) {
                $selected[$name] = $map[$name];
            }
        }

        if ($selected === []) {
            foreach ($this->defaultColumns() as $name) {
                if (isset($map[$name])) {
                    $selected[$name] = $map[$name];
                }
            }
        }

        return array_values($selected);
    }

    /**
     * Restrict the query to what the columns need: their database columns (plus
     * the primary key and relation keys), eager loads and counts.
     *
     * @param  array<int, Column>  $columns
     */
    public function prepare(Builder $query, array $columns): Builder
    {
        $model = $query->getModel();
        $selects = [$model->getQualifiedKeyName()];
        $everything = false;
        $relations = [];
        $counts = [];

        foreach ($columns as $column) {
            foreach ($column->selectColumns() as $select) {
                if ($select === '*') {
                    $everything = true;
                } else {
                    $selects[] = str_contains($select, '.') ? $select : $model->qualifyColumn($select);
                }
            }

            foreach ($column->relations() as $relation) {
                $relations[] = $relation;
                array_push($selects, ...$this->relationKeys($model, $relation));
            }

            array_push($counts, ...$column->counts());
        }

        if (! $everything) {
            $query->select(array_values(array_unique($selects)));
        }

        if ($relations !== []) {
            $query->with(array_values(array_unique($relations)));
        }

        if ($counts !== []) {
            $query->withCount(array_values(array_unique($counts)));
        }

        return $query;
    }

    /**
     * Local keys the relation needs on this model to be loadable.
     *
     * @return array<int, string>
     */
    protected function relationKeys(Model $model, string $relation): array
    {
        $relation = $model->{Str::before($relation, '.')}();

        return match (true) {
            $relation instanceof MorphTo => [
                $model->qualifyColumn($relation->getForeignKeyName()),
                $model->qualifyColumn($relation->getMorphType()),
            ],
            $relation instanceof BelongsTo => [$model->qualifyColumn($relation->getForeignKeyName())],
            $relation instanceof HasOneOrMany => [$model->qualifyColumn($relation->getLocalKeyName())],
            default => [],
        };
    }

    /**
     * Entries for the repository's `orderingFields()`.
     *
     * @return array<string, mixed>
     */
    public function orderingFields(Model $model): array
    {
        $fields = [];
        foreach ($this->columnMap() as $name => $column) {
            if (! is_null($ordering = $column->ordering($model))) {
                $fields[$name] = $ordering;
            }
        }

        return $fields;
    }

    /**
     * One row of the response: the primary key plus a value per column.
     *
     * @param  array<int, Column>  $columns
     */
    public function row(Model $model, array $columns): array
    {
        $row = [$model->getKeyName() => $model->getKey()];
        foreach ($columns as $column) {
            $row[$column->name()] = $column->resolve($model);
        }

        return $row;
    }

    /**
     * @param  iterable<Model>  $models
     * @param  array<int, Column>  $columns
     * @return array<int, array>
     */
    public function rows(iterable $models, array $columns): array
    {
        $rows = [];
        foreach ($models as $model) {
            $rows[] = $this->row($model, $columns);
        }

        return $rows;
    }

    /**
     * Column definitions for the client (all columns) and the ones just returned.
     *
     * @param  array<int, Column>  $selected
     */
    public function describe(array $selected): array
    {
        return [
            'columns' => array_values(array_map(fn (Column $column) => $column->describe(), $this->columnMap())),
            'selected' => array_map(fn (Column $column) => $column->name(), $selected),
        ];
    }
}

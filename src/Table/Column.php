<?php

namespace FancyCrud\Table;

use Closure;
use FancyCrud\Filters\Filter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One column of a {@see BaseTable}: what the client may ask for (`?fields[]=name`),
 * which database columns / relations it needs, how its value is read, whether it
 * can be ordered, and which filters it brings along.
 *
 * ```php
 * Column::text('title')->orderable()->filterable('LIKE');
 * Column::text('author_name')->relation('author', 'name')->orderable(fn ($q, $dir) => …);
 * Column::text('comments_count')->count('comments')->orderable();
 * Column::image('cover')->disk('public');
 * Column::link('title')->url(fn (Model $row) => route('posts.show', $row));
 * Column::switch('is_active')->filterable();
 * Column::actions(['show', 'update', 'delete']);
 * ```
 */
class Column
{
    public const string TEXT = 'text';

    public const string IMAGE = 'image';

    public const string LINK = 'link';

    public const string SWITCH = 'switch';

    public const string ACTIONS = 'actions';

    protected ?string $label = null;

    protected bool $default = true;

    /** @var array<int, string>|null Database columns this column needs; null = derive. */
    protected ?array $select = null;

    protected ?string $relation = null;

    protected ?string $relationColumn = null;

    /** Dot path read from the model (`meta.color`, `author.name`); null = the column name. */
    protected ?string $path = null;

    /** @var array<int, string> */
    protected array $counts = [];

    protected ?Closure $getter = null;

    protected bool|string|Closure $order = false;

    /** @var array<int, Filter> */
    protected array $filters = [];

    /** @var array{0: string, 1: string}|null */
    protected ?array $filterable = null;

    protected ?string $disk = null;

    protected ?Closure $url = null;

    /** @var array<int, string> */
    protected array $abilities = ['show', 'update', 'delete'];

    protected array $meta = [];

    public function __construct(protected string $name, protected string $type = self::TEXT) {}

    public static function make(string $name, string $type = self::TEXT): static
    {
        return new static($name, $type);
    }

    public static function text(string $name): static
    {
        return new static($name, self::TEXT);
    }

    public static function image(string $name): static
    {
        return new static($name, self::IMAGE);
    }

    public static function link(string $name): static
    {
        return new static($name, self::LINK);
    }

    public static function switch(string $name): static
    {
        return new static($name, self::SWITCH);
    }

    /**
     * The row-actions column: for each ability, whether the current user may
     * perform it on that row (`Gate::allows($ability, $row)`).
     *
     * @param  array<int, string>  $abilities
     */
    public static function actions(array $abilities = ['show', 'update', 'delete'], string $name = 'actions'): static
    {
        $column = new static($name, self::ACTIONS);
        $column->abilities = $abilities;
        $column->meta['abilities'] = $abilities;

        return $column;
    }

    // ---- definition ------------------------------------------------------

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Whether the column is part of the default set (no `fields` in the request).
     */
    public function default(bool $default = true): static
    {
        $this->default = $default;

        return $this;
    }

    /**
     * Shortcut for `default(false)`: only returned when asked for.
     */
    public function optional(): static
    {
        return $this->default(false);
    }

    /**
     * The database columns this column needs. Required when the value comes from
     * an accessor or a custom getter; otherwise the column name is used.
     *
     * @param  string|array<int, string>  $columns
     */
    public function select(string|array $columns): static
    {
        $this->select = (array) $columns;

        return $this;
    }

    /**
     * Read the value through a relation: `relation('author', 'name')` eager-loads
     * `author` and returns `author.name`. The foreign key is selected automatically.
     */
    public function relation(string $relation, ?string $column = null): static
    {
        $this->relation = $relation;
        $this->relationColumn = $column;
        $this->path = $column ? "{$relation}.{$column}" : $relation;

        return $this;
    }

    /**
     * Read the value from another path of the model (dot notation, e.g. a JSON
     * attribute `meta.color`). The first segment is selected unless `select()` says otherwise.
     */
    public function from(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Add `withCount($relation)`; the column name should be `{relation}_count`.
     */
    public function count(string $relation): static
    {
        $this->counts[] = $relation;

        return $this;
    }

    /**
     * Fully custom value: `fn (Model $row) => …`. Declare what it reads with
     * `select()` / `relation()`, otherwise every column of the model is selected.
     */
    public function valueUsing(Closure $getter): static
    {
        $this->getter = $getter;

        return $this;
    }

    /**
     * Storage disk used to turn an image path into a URL.
     */
    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    /**
     * Link target: `fn (Model $row) => string`. The value becomes `{label, url}`.
     */
    public function url(Closure $url): static
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Free-form data sent to the client with the column definition.
     */
    public function meta(array $meta): static
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    // ---- ordering --------------------------------------------------------

    /**
     * Allow `?order_by[][name]=<column>`:
     *  - `true`      → order by the column itself;
     *  - `'column'`  → order by another database column;
     *  - `Closure`   → `fn (Builder $query, string $direction)` for custom ordering.
     */
    public function orderable(bool|string|Closure $order = true): static
    {
        $this->order = $order;

        return $this;
    }

    public function isOrderable(): bool
    {
        return $this->order !== false;
    }

    /**
     * The entry for the repository's `orderingFields()`, or null when not orderable.
     */
    public function ordering(Model $model): string|Closure|null
    {
        if ($this->order === false) {
            return null;
        }

        if ($this->order instanceof Closure || is_string($this->order)) {
            return $this->order;
        }

        $own = $this->selectColumns();
        if ($this->counts !== [] || count($own) !== 1 || $own[0] === '*') {
            return $this->name;
        }

        return $model->getTable().'.'.$own[0];
    }

    // ---- filters ---------------------------------------------------------

    /**
     * Filter on this column: `?<name>=value`. Applied through the relation for
     * relation columns, on the real database column otherwise.
     */
    public function filterable(string $condition = '=', string $method = 'where'): static
    {
        $this->filterable = [$condition, $method];

        return $this;
    }

    /**
     * Attach a hand-built filter (e.g. a range or a callback).
     */
    public function filter(Filter ...$filters): static
    {
        array_push($this->filters, ...$filters);

        return $this;
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(): array
    {
        $filters = $this->filters;

        if ($this->filterable !== null) {
            [$condition, $method] = $this->filterable;
            $filter = Filter::make($this->name)->condition($condition)->method($method);

            if ($this->relation !== null) {
                $filter->relation($this->relation)->column($this->relationColumn ?? $this->name);
            } else {
                $filter->column($this->ownColumn());
            }

            array_unshift($filters, $filter);
        }

        return $filters;
    }

    // ---- query requirements ---------------------------------------------

    /**
     * Database columns to select; `['*']` when unknown (custom getter without `select()`).
     *
     * @return array<int, string>
     */
    public function selectColumns(): array
    {
        if ($this->select !== null) {
            return $this->select;
        }

        if ($this->type === self::ACTIONS || $this->relation !== null || $this->counts !== []) {
            return [];
        }

        if ($this->getter !== null) {
            return ['*'];
        }

        return [$this->ownColumn()];
    }

    /**
     * The single database column this column maps to (filter / order target).
     */
    protected function ownColumn(): string
    {
        if ($this->select !== null && count($this->select) === 1) {
            return $this->select[0];
        }

        if ($this->path !== null && $this->relation === null) {
            return Str::before($this->path, '.');
        }

        return $this->name;
    }

    /**
     * Relations to eager-load.
     *
     * @return array<int, string>
     */
    public function relations(): array
    {
        return $this->relation !== null ? [$this->relation] : [];
    }

    /**
     * @return array<int, string>
     */
    public function counts(): array
    {
        return $this->counts;
    }

    // ---- value -----------------------------------------------------------

    /**
     * The value sent to the client for this row.
     */
    public function resolve(Model $row): mixed
    {
        if ($this->getter !== null) {
            return ($this->getter)($row);
        }

        if ($this->type === self::ACTIONS) {
            $allowed = [];
            foreach ($this->abilities as $ability) {
                $allowed[$ability] = Gate::allows($ability, $row);
            }

            return $allowed;
        }

        $raw = data_get($row, $this->path ?? $this->name);

        return match ($this->type) {
            self::IMAGE => $this->imageUrl($raw),
            self::LINK => $this->linkValue($raw, $row),
            self::SWITCH => is_null($raw) ? null : (bool) $raw,
            default => $raw,
        };
    }

    protected function imageUrl(mixed $raw): mixed
    {
        if (! is_string($raw) || $raw === '' || $this->disk === null || Str::startsWith($raw, ['http://', 'https://', '//'])) {
            return $raw;
        }

        return Storage::disk($this->disk)->url($raw);
    }

    protected function linkValue(mixed $raw, Model $row): mixed
    {
        if (is_null($raw)) {
            return null;
        }

        return ['label' => $raw, 'url' => $this->url ? ($this->url)($row) : $raw];
    }

    // ---- accessors -------------------------------------------------------

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function isDefault(): bool
    {
        return $this->default;
    }

    public function getLabel(): string
    {
        return $this->label ?? Str::headline($this->name);
    }

    /**
     * What the client receives to render headers / pickers.
     */
    public function describe(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->getLabel(),
            'type' => $this->type,
            'default' => $this->default,
            'orderable' => $this->isOrderable(),
            'filterable' => array_map(fn (Filter $filter) => $filter->name(), $this->filters()),
            'meta' => $this->meta,
        ];
    }
}

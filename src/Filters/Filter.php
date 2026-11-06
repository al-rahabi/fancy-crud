<?php

namespace FancyCrud\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * One index filter: the request key it reads (`name`), how it is applied
 * (`method` + `condition`), and optionally a fixed `value`, a `relation` to
 * apply it through, or a custom `query` callback.
 *
 * ```php
 * Filter::make('status');                                  // where status = ?status
 * Filter::make('title')->condition('LIKE');                 // where title LIKE ?title
 * Filter::make('created_at')->method('whereDate');          // whereDate(created_at, =, ?created_at)
 * Filter::make('name')->relation('author');                 // whereHas author: name = ?name
 * Filter::make('author')->column('author_id');              // ?author=3 → where author_id = 3
 * Filter::make('status')->rules('in:draft,published');      // validated by the index request
 * Filter::make('mine')->using(fn ($q, $v) => $q->where('user_id', (int) $v));
 * ```
 */
class Filter
{
    protected ?string $column = null;

    /** @var array<int, mixed> */
    protected array $rules = ['nullable'];

    protected string $condition = '=';

    protected string $method = 'where';

    protected ?string $relation = null;

    protected mixed $value = null;

    protected ?Closure $query = null;

    public function __construct(protected string $name) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * The database column when it differs from the request key (default: the name).
     */
    public function column(string $column): static
    {
        $this->column = $column;

        return $this;
    }

    public function getColumn(): string
    {
        return $this->column ?? $this->name;
    }

    /**
     * Validation rules of the request value (default: `nullable`, any value).
     *
     * @param  string|array<int, mixed>  $rules
     */
    public function rules(string|array $rules): static
    {
        $this->rules = (array) $rules;

        return $this;
    }

    /**
     * @return array<int, mixed>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Comparison operator (default `=`).
     */
    public function condition(string $condition): static
    {
        $this->condition = $condition;

        return $this;
    }

    /**
     * Builder method used to apply the filter (default `where`, e.g. `whereDate`).
     */
    public function method(string $method): static
    {
        $this->method = $method;

        return $this;
    }

    /**
     * Apply the filter through `whereHas($relation, …)`.
     */
    public function relation(string $relation): static
    {
        $this->relation = $relation;

        return $this;
    }

    /**
     * A fixed value that wins over the request value.
     */
    public function value(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Custom callback `fn(Builder $query, mixed $value)`, wrapped in a `where` group.
     */
    public function using(callable $query): static
    {
        $this->query = Closure::fromCallable($query);

        return $this;
    }

    /**
     * The fixed value if set, otherwise the one the request carried (or null).
     */
    public function resolveValue(array $requested): mixed
    {
        return $this->value ?? $requested[$this->name] ?? null;
    }

    /**
     * Apply to the query; a null value means "not filtered" and is skipped.
     */
    public function apply(Builder $query, array $requested): Builder
    {
        $value = $this->resolveValue($requested);
        if (is_null($value)) {
            return $query;
        }

        $method = $this->method;

        if ($this->relation !== null) {
            return $query->whereHas(
                $this->relation,
                fn (Builder $sub) => $sub->$method($this->getColumn(), $this->condition, $value)
            );
        }

        if ($this->query !== null) {
            return $query->where(fn (Builder $sub) => ($this->query)($sub, $value));
        }

        return $query->$method($this->getColumn(), $this->condition, $value);
    }
}

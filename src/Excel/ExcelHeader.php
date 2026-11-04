<?php

namespace FancyCrud\Excel;

use FancyCrud\Excel\Enums\ExcelColumnSource;
use FancyCrud\Excel\Enums\ExcelColumnType;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Describes one column of an exported / imported sheet: what it is called, where
 * its value comes from, and what type the cell holds.
 *
 * ```php
 * public function getHeaders(): array
 * {
 *     return [
 *         ExcelHeader::column('name', 'الاسم')->rules(['required', 'string', 'max:180']),
 *         ExcelHeader::column('is_active', 'مفعل')->type(ExcelColumnType::BOOLEAN),
 *         ExcelHeader::relation('subject.name', 'المادة')
 *             ->attribute('subject_id')
 *             ->importUsing(fn($value) => Subject::query()->where('name', $value)->value('id')),
 *         ExcelHeader::query('lectures_count', 'عدد المحاضرات', fn(Builder $q) => $q->withCount('lectures'))
 *             ->type(ExcelColumnType::INTEGER)
 *             ->importable(false),
 *         ExcelHeader::calculated('full_name', 'الاسم الكامل', fn(Model $m) => "{$m->first_name} {$m->last_name}")
 *             ->importable(false),
 *     ];
 * }
 * ```
 */
class ExcelHeader
{
    protected string $key;
    protected string $label;
    protected ExcelColumnSource $source = ExcelColumnSource::COLUMN;
    protected ExcelColumnType $type = ExcelColumnType::TEXT;
    protected ?string $attribute = null;
    protected ?Closure $using = null;
    protected ?Closure $queryUsing = null;
    protected ?Closure $importUsing = null;
    protected array $rules = [];
    protected array $options = [];
    protected bool $exportable = true;
    protected bool $importable = true;
    protected bool $required = false;
    protected mixed $example = null;

    public function __construct(string $key, ?string $label = null)
    {
        $this->key = $key;
        $this->label = $label ?? Str::headline(str_replace('.', ' ', $key));
    }

    public static function make(string $key, ?string $label = null): static
    {
        return new static($key, $label);
    }

    /**
     * A column on the model's own table.
     */
    public static function column(string $key, ?string $label = null): static
    {
        return static::make($key, $label)->source(ExcelColumnSource::COLUMN);
    }

    /**
     * A dot path through relations, e.g. `subject.name`. Load the relation in the
     * controller's `getRelations()` or the export runs a query per row.
     */
    public static function relation(string $path, ?string $label = null): static
    {
        return static::make($path, $label)->source(ExcelColumnSource::RELATION);
    }

    /**
     * A value produced by the query itself — `withCount`, `selectSub`, a join alias.
     * The closure receives the export query and must return it.
     *
     * @param Closure(Builder): Builder $query
     */
    public static function query(string $key, ?string $label, Closure $query): static
    {
        return static::make($key, $label)->source(ExcelColumnSource::QUERY)->queryUsing($query);
    }

    /**
     * A value computed in PHP from the model.
     *
     * @param Closure(Model): mixed $using
     */
    public static function calculated(string $key, ?string $label, Closure $using): static
    {
        return static::make($key, $label)->source(ExcelColumnSource::CALCULATED)->using($using);
    }

    public function source(ExcelColumnSource $source): static
    {
        $this->source = $source;
        return $this;
    }

    public function type(ExcelColumnType $type): static
    {
        $this->type = $type;
        return $this;
    }

    /**
     * The payload key this column fills on import. Defaults to the header key —
     * set it when the sheet holds a human value for a different attribute
     * (`subject.name` in the sheet, `subject_id` in the payload).
     */
    public function attribute(string $attribute): static
    {
        $this->attribute = $attribute;
        return $this;
    }

    /**
     * @param Closure(Model): mixed $using
     */
    public function using(Closure $using): static
    {
        $this->using = $using;
        return $this;
    }

    /**
     * @param Closure(Builder): Builder $query
     */
    public function queryUsing(Closure $query): static
    {
        $this->queryUsing = $query;
        return $this;
    }

    /**
     * Translate the raw cell into the value stored on the model. Receives the cast
     * cell value and the whole mapped row.
     *
     * @param Closure(mixed, array): mixed $using
     */
    public function importUsing(Closure $using): static
    {
        $this->importUsing = $using;
        return $this;
    }

    public function rules(array $rules): static
    {
        $this->rules = $rules;
        return $this;
    }

    /**
     * Restrict the column to a set of values: validated with `Rule::in` and listed
     * in the template so the customer knows what to type.
     */
    public function options(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;
        return $this;
    }

    public function exportable(bool $exportable = true): static
    {
        $this->exportable = $exportable;
        return $this;
    }

    public function importable(bool $importable = true): static
    {
        $this->importable = $importable;
        return $this;
    }

    public function example(mixed $example): static
    {
        $this->example = $example;
        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getType(): ExcelColumnType
    {
        return $this->type;
    }

    public function getSource(): ExcelColumnSource
    {
        return $this->source;
    }

    public function getAttribute(): string
    {
        return $this->attribute ?? $this->key;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function isExportable(): bool
    {
        return $this->exportable;
    }

    public function isImportable(): bool
    {
        return $this->importable;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Let a column contribute to the export query — a `withCount`/`selectSub` for a
     * QUERY column, or the eager load a RELATION / CALCULATED column reads from.
     */
    public function applyToQuery(Builder $query): Builder
    {
        if ($this->queryUsing) {
            return ($this->queryUsing)($query) ?? $query;
        }

        return $query;
    }

    /**
     * The raw value for this column, before casting.
     */
    public function valueFor(Model $model): mixed
    {
        return match ($this->source) {
            ExcelColumnSource::CALCULATED => $this->using ? ($this->using)($model) : null,
            default => data_get($model, $this->key),
        };
    }

    /**
     * The value written into the sheet.
     */
    public function exportValue(Model $model): mixed
    {
        return $this->type->castForExport($this->valueFor($model));
    }

    /**
     * The number format applied to this column in the sheet, if the type needs one.
     */
    public function numberFormat(): ?string
    {
        return $this->type->numberFormat();
    }

    /**
     * Cast a raw cell, then hand it to `importUsing()` if one is defined.
     */
    public function importValue(mixed $value, array $row = []): mixed
    {
        $value = $this->type->castForImport($value);

        if ($this->importUsing) {
            return ($this->importUsing)($value, $row);
        }

        return $value;
    }

    /**
     * Rules for this column alone: `required`/`nullable`, the type rule, the
     * option list, then whatever the caller declared.
     */
    public function validationRules(): array
    {
        if ($this->rules !== []) {
            return $this->rules;
        }

        $rules = [$this->required ? 'required' : 'nullable', ...$this->type->validationRules()];

        if ($this->options !== []) {
            $rules[] = Rule::in($this->options);
        }

        return $rules;
    }

    /**
     * The cell written into the template row so the customer sees the expected shape.
     */
    public function exampleValue(): string
    {
        if (!is_null($this->example)) {
            return (string)$this->example;
        }

        if ($this->options !== []) {
            return implode(' | ', $this->options);
        }

        return $this->type->exampleValue();
    }
}

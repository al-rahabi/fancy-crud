<?php

namespace FancyCrud\Excel;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * The sheet definition for one resource: which columns it has, how a row is
 * validated, and how a validated row becomes a record.
 *
 * A controller points at one with the `$excel` attribute, next to `$model` and
 * `$resource`:
 *
 * ```php
 * class CourseController extends CrudController implements Exportable, Importable
 * {
 *     use ExportTrait, ImportTrait;
 *
 *     protected string $model = Course::class;
 *     protected ?string $resource = CourseResource::class;
 *     protected ?string $excel = CourseExcel::class;
 * }
 * ```
 *
 * The class knows nothing about HTTP: the controller keeps the permission check
 * and the export query, this keeps the shape of the sheet. That also makes it
 * usable straight from a console command or a test.
 */
abstract class BaseExcel
{
    /** The model rows are created on. */
    protected string $model = Model::class;

    /** Models loaded per database chunk while exporting. */
    protected int $exportChunkSize = 500;

    /** Rows validated and written per chunk while importing. */
    protected int $importChunkSize = 500;

    /** Data rows accepted per upload; null reads the whole file. */
    protected ?int $importRowLimit = 5000;

    /** Name => id maps, resolved once per import instead of once per row. */
    protected array $lookups = [];

    /** Validated request fields that came with the upload. */
    protected array $context = [];

    /**
     * The columns of the sheet, in order.
     *
     * @return ExcelHeader[]
     */
    abstract public function headers(): array;

    /**
     * Fields the import needs **beside** the file, validated with it on the same
     * request and then available through `context()`. Use them for values that
     * belong to the upload as a whole rather than to a row — which course these
     * rows belong to, which subject they are for.
     *
     * ```php
     * public function requestRules(): array
     * {
     *     return ['name' => 'required|string|max:180', 'subject_id' => 'required|exists:subjects,id'];
     * }
     * ```
     */
    public function requestRules(): array
    {
        return [];
    }

    public function withContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * The validated request data, or one key of it.
     */
    public function context(?string $key = null, mixed $default = null): mixed
    {
        return is_null($key) ? $this->context : ($this->context[$key] ?? $default);
    }

    /**
     * Runs once with the uploaded sheet's heading row, before its columns are
     * matched. A sheet whose columns depend on the file — any number of answer
     * columns, say — adjusts itself here; `headers()` and `rules()` are read again
     * afterwards.
     *
     * @param array<int, mixed> $cells the raw first row of the file
     */
    public function withHeadingRow(array $cells): void
    {
        //
    }

    /**
     * Runs once before an export is written, with the query it reads — the place to
     * size columns that depend on the data. `headers()` is read afterwards.
     */
    public function beforeExport(Builder $query): void
    {
        //
    }

    /**
     * Runs once before the first row is read — after `requestRules()` has been
     * validated, so `context()` is populated. The place to create whatever the
     * rows hang off.
     */
    public function beforeImport(): void
    {
        //
    }

    /**
     * Runs once after the last row, with the outcome. The place to undo setup that
     * turned out to be pointless, or to touch counters.
     */
    public function afterImport(ExcelImportResult $result): void
    {
        //
    }

    /**
     * Rules applied to every mapped row. Defaults to the rules each header
     * declares; override to validate across columns.
     */
    public function rules(): array
    {
        $rules = [];

        foreach ($this->headers() as $header) {
            if ($header instanceof ExcelHeader && $header->isImportable()) {
                $rules[$header->getAttribute()] = $header->validationRules();
            }
        }

        return $rules;
    }

    /**
     * Values added to every row before validation — the import equivalent of
     * `storeAdditionalData()`. Use it for columns the sheet does not carry.
     */
    public function additionalData(array $row): array
    {
        return [];
    }

    /**
     * Persist one validated row. Override when a row is more than one table: sync
     * a pivot, write child rows, and so on.
     */
    public function create(array $data): Model
    {
        return $this->model::query()->create($data);
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function exportChunkSize(): int
    {
        return $this->exportChunkSize;
    }

    public function importChunkSize(): int
    {
        return $this->importChunkSize;
    }

    public function importRowLimit(): ?int
    {
        return $this->importRowLimit;
    }

    /**
     * Resolve a name to an id through a table that is loaded at most once per
     * import:
     *
     * ```php
     * ->importUsing(fn($value) => $this->lookup('subjects', fn() => Subject::query()->pluck('id', 'name'), $value))
     * ```
     */
    protected function lookup(string $key, Closure $resolver, ?string $name): ?int
    {
        if (blank($name)) {
            return null;
        }

        $this->lookups[$key] ??= $resolver();

        return $this->lookups[$key][$name] ?? null;
    }

    /**
     * Split a cell holding a list — "أول | ثانٍ" or "1, 2, 3" — into its entries.
     */
    protected function splitList(?string $value): Collection
    {
        return collect(preg_split('/[|,]/', (string)$value))
            ->map(fn($entry) => trim($entry))
            ->filter(fn($entry) => $entry !== '')
            ->values();
    }

    /**
     * A rule for a column whose `importUsing()` resolves names to ids and leaves a
     * null wherever a name matched nothing — the row is rejected with one readable
     * message instead of the record being created with the relation half-attached.
     */
    protected function unknownNamesRule(string $messageKey): Closure
    {
        return function ($attribute, $value, $fail) use ($messageKey) {
            if (in_array(null, Arr::wrap($value), true)) {
                $fail(__($messageKey));
            }
        };
    }
}

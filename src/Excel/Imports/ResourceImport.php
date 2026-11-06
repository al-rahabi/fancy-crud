<?php

namespace FancyCrud\Excel\Imports;

use FancyCrud\Excel\Contracts\Importable;
use FancyCrud\Excel\ExcelHeader;
use FancyCrud\Excel\ExcelImportResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Row;
use Throwable;

/**
 * Reads a filled-in sheet back into models, one row at a time.
 *
 * `OnEachRow` hands over the real sheet row, so a rejected row is reported with
 * the line number the customer sees in Excel. `WithChunkReading` keeps the file
 * off the heap: Excel reads it in slices of `chunkSize()`, and each slice runs in
 * its own transaction.
 *
 * Validation is done here rather than through Excel's `WithValidation` because
 * the rules are keyed by model attribute while the sheet is keyed by column — the
 * headers are what map one onto the other.
 */
class ResourceImport implements OnEachRow, WithChunkReading, WithLimit
{
    /** Rows read when the controller sets no cap. */
    public const MAX_ROWS = 100000;

    protected Importable $controller;

    /** @var ExcelHeader[] */
    protected array $headers = [];

    protected ExcelImportResult $result;

    protected int $chunkSize;

    protected ?int $rowLimit;

    /** @var array<int, ExcelHeader> column index => header */
    protected array $columns = [];

    /** Built once per run, not once per row. */
    protected array $rules = [];

    protected array $labels = [];

    protected int $processed = 0;

    public function __construct(
        Importable $controller,
        ExcelImportResult $result,
        int $chunkSize = 500,
        ?int $rowLimit = null
    ) {
        $this->controller = $controller;
        $this->result = $result;
        $this->chunkSize = max(1, $chunkSize);
        $this->rowLimit = $rowLimit;
    }

    public function onRow(Row $row): void
    {
        // Raw values: no formatting applied, so a date cell arrives as its Excel
        // serial and ExcelColumnType turns it back into a date.
        $values = $row->toArray(null, false, false);

        if ($this->columns === []) {
            $this->mapColumns($values);

            return;
        }

        if ($this->isEmptyRow($values)) {
            $this->result->addSkipped();

            return;
        }

        if (! is_null($this->rowLimit) && $this->processed >= $this->rowLimit) {
            return;
        }

        $this->processed++;
        $this->processRow($row->getIndex(), $values);
    }

    public function chunkSize(): int
    {
        return $this->chunkSize;
    }

    /**
     * Counted from row 1, so the heading row has to be included in the cap.
     */
    public function limit(): int
    {
        return ($this->rowLimit ?? self::MAX_ROWS) + 1;
    }

    /**
     * Match the sheet's first row against the declared headers, by label first and
     * by key second, so both a file produced by the exporter and one written by a
     * developer line up.
     *
     * The sheet sees the row first, so the headers and rules are only read here —
     * they may depend on what the file holds (any number of answer columns).
     */
    protected function mapColumns(array $firstRow): void
    {
        $this->controller->prepareImport($firstRow);

        $this->headers = array_values(array_filter(
            $this->controller->getHeaders(),
            fn ($header) => $header instanceof ExcelHeader && $header->isImportable()
        ));
        $this->rules = $this->controller->importRules();

        $byName = [];
        foreach ($this->headers as $header) {
            $byName[$this->normalize($header->getKey())] = $header;
            $byName[$this->normalize($header->getLabel())] = $header;
        }

        foreach ($firstRow as $index => $value) {
            $candidate = $this->normalize(is_scalar($value) ? (string) $value : '');

            if ($candidate !== '' && isset($byName[$candidate])) {
                $this->columns[$index] = $byName[$candidate];
            }
        }

        abort_if($this->columns === [], 400, __('fancy-crud::messages.excel_no_matching_columns'));

        $this->labels = [];
        foreach ($this->columns as $header) {
            $this->labels[$header->getAttribute()] = $header->getLabel();
        }
    }

    protected function processRow(int $line, array $values): void
    {
        $data = $this->mapRow($values);
        $data = array_merge($data, $this->controller->importAdditionalData($data));

        $validator = Validator::make($data, $this->rules, [], $this->labels);

        if ($validator->fails()) {
            $this->result->addError($line, $validator->errors()->toArray());

            return;
        }

        try {
            DB::transaction(function () use ($data) {
                if (method_exists($this->controller, 'createForExcel')) {
                    $this->controller->createForExcel($data);

                    return;
                }

                $this->controller->storeExcelRow($data);
            });

            $this->result->addImported();
        } catch (Throwable $exception) {
            Log::error('Excel import row failed', ['line' => $line, 'exception' => $exception]);

            $this->result->addError($line, [
                'row' => [app()->isLocal() ? $exception->getMessage() : __('fancy-crud::messages.excel_row_failed')],
            ]);
        }
    }

    /**
     * Cell values → model attributes. Casting runs first for the whole row, so an
     * `importUsing()` closure can look at its neighbours.
     */
    protected function mapRow(array $values): array
    {
        $cast = [];
        foreach ($this->columns as $index => $header) {
            $cast[$header->getAttribute()] = $header->getType()->castForImport($values[$index] ?? null);
        }

        $data = [];
        foreach ($this->columns as $index => $header) {
            $data[$header->getAttribute()] = $header->importValue($values[$index] ?? null, $cast);
        }

        return $data;
    }

    protected function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if (! is_null($value) && (! is_string($value) || trim($value) !== '')) {
                return false;
            }
        }

        return true;
    }

    protected function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}

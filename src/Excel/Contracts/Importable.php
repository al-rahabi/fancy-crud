<?php

namespace FancyCrud\Excel\Contracts;

use FancyCrud\Excel\ExcelHeader;
use Illuminate\Database\Eloquent\Model;

/**
 * Implemented by `FancyCrud\Excel\Traits\ImportTrait`. A controller that uses the trait
 * declares `implements Importable`.
 *
 * `createForExcel(array $data): Model` is deliberately **not** part of this
 * contract: the importer checks for it with `method_exists()` and falls back to
 * `storeExcelRow()`. Declare it on a controller only when creating a row from a
 * sheet differs from creating one from the API.
 */
interface Importable
{
    /**
     * @return ExcelHeader[]
     */
    public function getHeaders(): array;

    /**
     * Hands the file's heading row to the sheet before its columns are matched, so
     * a sheet with file-dependent columns can size itself. `getHeaders()` and
     * `importRules()` are read after this.
     */
    public function prepareImport(array $headingRow): void;

    /**
     * Rules applied to every mapped row. Defaults to the headers' own rules.
     */
    public function importRules(): array;

    /**
     * Server-supplied values merged into each row before validation — the import
     * equivalent of `storeAdditionalData()`.
     */
    public function importAdditionalData(array $row): array;

    /**
     * How many rows are validated and written per chunk.
     */
    public function importChunkSize(): int;

    /**
     * Persist one validated row. The default implementation calls `createModel()`.
     */
    public function storeExcelRow(array $data): Model;
}

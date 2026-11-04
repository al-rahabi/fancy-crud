<?php

namespace FancyCrud\Excel\Contracts;

use FancyCrud\Excel\ExcelHeader;
use Illuminate\Database\Eloquent\Builder;

/**
 * Implemented by `FancyCrud\Excel\Traits\ExportTrait`. A controller that uses the trait
 * declares `implements Exportable` so the exporter can depend on the contract
 * instead of on the controller.
 */
interface Exportable
{
    /**
     * @return ExcelHeader[]
     */
    public function getHeaders(): array;

    /**
     * The rows to export — the index query, filters and ordering included.
     */
    public function exportQuery(): Builder;

    /**
     * Shows the export query to the sheet before its headers are read, so columns
     * that depend on the data can be sized.
     */
    public function prepareExport(Builder $query): void;

    /**
     * File name without extension.
     */
    public function exportFileName(): string;

    /**
     * How many models are loaded from the database at a time.
     */
    public function exportChunkSize(): int;
}

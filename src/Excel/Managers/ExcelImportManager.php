<?php

namespace FancyCrud\Excel\Managers;

use FancyCrud\Excel\Contracts\Importable;
use FancyCrud\Excel\ExcelImportResult;
use FancyCrud\Excel\Imports\ResourceImport;
use FancyCrud\Traits\Makeable;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Drives {@see ResourceImport} over an uploaded file.
 *
 * The file is read in chunks and a row that fails validation does not stop the
 * run: its line number and messages are collected in the {@see ExcelImportResult}
 * and the importer moves on to the next row.
 *
 * The controller is asked for `createForExcel()` first — when it defines that
 * method it owns row creation; otherwise `storeExcelRow()` (i.e. the normal
 * `createModel()`) is used.
 */
class ExcelImportManager
{
    use Makeable;

    protected Importable $controller;
    protected int $chunkSize = 500;
    protected ?int $limit = null;

    public function forController(Importable $controller): static
    {
        $this->controller = $controller;

        return $this->chunk($controller->importChunkSize());
    }

    public function chunk(int $chunkSize): static
    {
        $this->chunkSize = max(1, $chunkSize);
        return $this;
    }

    /**
     * Hard cap on how many data rows are read — a guard against someone uploading
     * a 200k-row sheet into a synchronous request.
     */
    public function limit(?int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @param UploadedFile|string $file the upload, or a path on the given disk
     * @param string|null $readerType one of the Maatwebsite\Excel\Excel constants;
     *                                only needed when the path has no extension
     */
    public function import(UploadedFile|string $file, ?string $readerType = null, ?string $disk = null): ExcelImportResult
    {
        $result = new ExcelImportResult();

        Excel::import(
            new ResourceImport($this->controller, $result, $this->chunkSize, $this->limit),
            $file,
            $disk,
            $readerType
        );

        return $result;
    }
}

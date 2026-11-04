<?php

namespace FancyCrud\Excel\Traits;

use FancyCrud\Excel\Contracts\Exportable;
use FancyCrud\Excel\Managers\ExcelExportManager;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Adds `GET {resource}/export` to a CrudController.
 *
 * By default the exported rows are the repository's `globalQuery()`; override
 * `exportQuery()` to apply the same filters and ordering as `index`. The columns
 * come from the `$excel` class.
 *
 * ```php
 * class LectureController extends CrudController implements Exportable
 * {
 *     use ExportTrait;
 *
 *     protected ?string $excel = LectureExcel::class;
 * }
 * ```
 */
trait ExportTrait
{
    use ExcelHeadersTrait;

    /**
     * Authorized through the policy's `export` ability.
     */
    public function export(): BinaryFileResponse
    {
        $this->authorizeExcel('export');

        return ExcelExportManager::make()->forController($this)->download();
    }

    /**
     * The rows to export — override to export something else.
     */
    public function exportQuery(): Builder
    {
        return $this->repository->globalQuery();
    }

    public function prepareExport(Builder $query): void
    {
        $this->excel()->beforeExport($query);
    }

    public function exportFileName(): string
    {
        return $this->excelFileName().'-'.now()->format('Y-m-d');
    }

    public function exportChunkSize(): int
    {
        return $this->excel()->exportChunkSize();
    }
}

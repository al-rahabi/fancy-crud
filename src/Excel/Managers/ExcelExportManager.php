<?php

namespace FancyCrud\Excel\Managers;

use FancyCrud\Excel\Contracts\Exportable;
use FancyCrud\Excel\ExcelHeader;
use FancyCrud\Excel\Exports\ResourceExport;
use FancyCrud\Excel\Exports\TemplateExport;
use FancyCrud\Traits\Makeable;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Builds the .xlsx behind `GET {resource}/export` and
 * `GET {resource}/import/template` from a set of {@see ExcelHeader}s.
 */
class ExcelExportManager
{
    use Makeable;

    /** @var ExcelHeader[] */
    protected array $headers = [];
    protected ?Builder $query = null;
    protected string $fileName = 'export';
    protected int $chunkSize = 500;

    public function forController(Exportable $controller): static
    {
        $query = $controller->exportQuery();

        // before the headers are read: the sheet may size its columns from the data
        $controller->prepareExport($query);

        return $this->headers($controller->getHeaders())
            ->query($query)
            ->fileName($controller->exportFileName())
            ->chunk($controller->exportChunkSize());
    }

    /**
     * @param ExcelHeader[] $headers
     */
    public function headers(array $headers): static
    {
        $this->headers = array_values(array_filter($headers, fn($header) => $header instanceof ExcelHeader));
        return $this;
    }

    public function query(?Builder $query): static
    {
        $this->query = $query;
        return $this;
    }

    public function fileName(string $fileName): static
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function chunk(int $chunkSize): static
    {
        $this->chunkSize = max(1, $chunkSize);
        return $this;
    }

    /**
     * The full export: one row per model behind the query.
     */
    public function download(): BinaryFileResponse
    {
        return Excel::download($this->export(), "{$this->fileName}.xlsx");
    }

    /**
     * The blank template: the header row plus a single example row showing the
     * expected shape of every importable column.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download($this->templateExport(), "{$this->fileName}.xlsx");
    }

    /**
     * Write the export to a path on a disk instead of returning a download.
     */
    public function store(string $path, ?string $disk = null): bool
    {
        return Excel::store($this->export(), $path, $disk);
    }

    public function export(): ResourceExport
    {
        return new ResourceExport($this->buildQuery(), $this->exportableHeaders(), $this->chunkSize);
    }

    public function templateExport(): TemplateExport
    {
        return new TemplateExport($this->importableHeaders());
    }

    /**
     * @return ExcelHeader[]
     */
    protected function exportableHeaders(): array
    {
        return array_values(array_filter($this->headers, fn(ExcelHeader $header) => $header->isExportable()));
    }

    /**
     * @return ExcelHeader[]
     */
    protected function importableHeaders(): array
    {
        return array_values(array_filter($this->headers, fn(ExcelHeader $header) => $header->isImportable()));
    }

    protected function buildQuery(): Builder
    {
        $query = $this->query ?? throw new RuntimeException('No query given to the exporter.');

        foreach ($this->exportableHeaders() as $header) {
            $query = $header->applyToQuery($query);
        }

        // The export reads the query with chunk(), which pages the result set, so
        // an unordered query could repeat or drop rows between pages.
        if (empty($query->getQuery()->orders)) {
            $query->orderBy($query->getModel()->getQualifiedKeyName());
        }

        return $query;
    }
}

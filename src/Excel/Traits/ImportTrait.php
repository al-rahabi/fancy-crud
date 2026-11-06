<?php

namespace FancyCrud\Excel\Traits;

use FancyCrud\Excel\Contracts\Importable;
use FancyCrud\Excel\Managers\ExcelExportManager;
use FancyCrud\Excel\Managers\ExcelImportManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Adds `GET {resource}/import/template` and `POST {resource}/import` to a CRUD
 * controller.
 *
 * The template is the empty sheet handed to the customer: one labelled column per
 * importable header plus an example row. The filled file comes back to `import`,
 * which reads it in chunks and answers with `{ imported, failed, skipped, errors }`
 * — a row that fails validation is reported by line number and skipped, it never
 * aborts the run.
 *
 * Everything about the sheet — columns, rules, defaults, how a row is written —
 * lives in the `$excel` class:
 *
 * ```php
 * class LectureController extends CrudController implements Importable
 * {
 *     use ImportTrait;
 *
 *     protected ?string $excel = LectureExcel::class;
 *
 *     // optional — only when a sheet row needs the controller rather than the
 *     // Excel class to create it
 *     public function createForExcel(array $data): Model { … }
 * }
 * ```
 */
trait ImportTrait
{
    use ExcelHeadersTrait;

    protected array $importExtensions = ['xlsx', 'csv', 'ods'];

    /** Max upload size in kilobytes. */
    protected int $importMaxSize = 20480;

    /**
     * Authorized through the policy's `import` ability.
     */
    public function import(Request $request): JsonResponse
    {
        $this->authorizeExcel('import');

        $excel = $this->excel();

        $data = $request->validate([
            'file' => [
                'required',
                'file',
                'extensions:'.implode(',', $this->importExtensions),
                'max:'.$this->importMaxSize,
            ],
            ...$excel->requestRules(),
        ]);

        // Everything the request carried besides the file is context for the run:
        // available to the sheet through context(), before a single row is read.
        $excel->withContext(Arr::except($data, ['file']));
        $excel->beforeImport();

        $result = ExcelImportManager::make()
            ->forController($this)
            ->limit($this->importRowLimit())
            ->import($request->file('file'));

        $excel->afterImport($result);

        return $this->success($result->toArray(), message: __('fancy-crud::messages.excel_import_finished'));
    }

    /**
     * The blank sheet the customer fills in. Gated by the `import` ability —
     * its only purpose is to be sent back to `import`.
     */
    public function importTemplate(): BinaryFileResponse
    {
        $this->authorizeExcel('import');

        return ExcelExportManager::make()
            ->headers($this->getHeaders())
            ->fileName($this->excelFileName().'-template')
            ->downloadTemplate();
    }

    public function prepareImport(array $headingRow): void
    {
        $this->excel()->withHeadingRow($headingRow);
    }

    public function importRules(): array
    {
        return $this->excel()->rules();
    }

    public function importAdditionalData(array $row): array
    {
        return $this->excel()->additionalData($row);
    }

    public function importChunkSize(): int
    {
        return $this->excel()->importChunkSize();
    }

    public function importRowLimit(): ?int
    {
        return $this->excel()->importRowLimit();
    }

    /**
     * Default row persistence: `BaseExcel::create()`. A controller that defines
     * `createForExcel(array $data): Model` takes over instead — the importer looks
     * for that method first.
     */
    public function storeExcelRow(array $data): Model
    {
        return $this->excel()->create($data);
    }
}

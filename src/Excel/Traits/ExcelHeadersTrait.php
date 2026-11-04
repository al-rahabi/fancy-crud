<?php

namespace FancyCrud\Excel\Traits;

use FancyCrud\Excel\BaseExcel;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Resolves the controller's `$excel` attribute — the sheet definition shared by
 * {@see ExportTrait} and {@see ImportTrait}, so a resource describes its columns
 * once and both directions use it.
 */
trait ExcelHeadersTrait
{
    /**
     * The sheet definition itself is named by the controller's `$excel` attribute,
     * declared on `BaseCrudController` next to `$model` and `$resource` — a trait
     * cannot hold it, because a subclass giving it a different default would be an
     * incompatible property composition.
     */
    private ?BaseExcel $excelInstance = null;

    /**
     * The sheet definition, built once per request.
     */
    public function excel(): BaseExcel
    {
        if (is_null($this->excel)) {
            throw new RuntimeException(static::class . ' uses the Excel traits but declares no $excel class.');
        }

        return $this->excelInstance ??= new $this->excel();
    }

    /**
     * Policy check for `export` / `import` against the controller's model.
     * Override to plug in another authorization scheme.
     */
    protected function authorizeExcel(string $ability): void
    {
        // No `$excel` class declared: the endpoints simply don't exist for this resource.
        abort_if(is_null($this->excel), 404);

        $this->authorize($ability, $this->repository->getModel());
    }

    /**
     * @return \FancyCrud\Excel\ExcelHeader[]
     */
    public function getHeaders(): array
    {
        return $this->excel()->headers();
    }

    /**
     * Base name of every file produced for this resource, derived from the
     * model name: `Lecture` → `lectures`.
     */
    public function excelFileName(): string
    {
        return Str::slug(Str::plural(Str::kebab(class_basename($this->repository->getModel()))));
    }
}

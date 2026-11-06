<?php

namespace FancyCrud\Contracts;

use FancyCrud\Bulk\BulkRunner;
use FancyCrud\Excel\Contracts\Exportable;
use FancyCrud\Excel\Contracts\Importable;
use FancyCrud\Excel\Traits\ExportTrait;
use FancyCrud\Excel\Traits\ImportTrait;
use FancyCrud\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

/**
 * Excel support is built in and optional: set `$excel` to a `BaseExcel` class and
 * `export()`, `import()` and `importTemplate()` work; leave it null and they
 * answer 404. Any of those methods (or `exportQuery()`, `createForExcel()`, …)
 * defined on a concrete controller overrides the default.
 */
abstract class CrudController extends Controller implements Exportable, Importable
{
    use ExportTrait, ImportTrait;

    protected BaseRepository $repository;

    protected ?string $resource = null;

    protected ?string $storeRequest = null;

    protected ?string $updateRequest = null;

    /** Sheet definition (a FancyCrud\Excel\BaseExcel class) used by ExportTrait / ImportTrait. */
    protected ?string $excel = null;

    public function __construct()
    {
        parent::__construct();

        $this->repository = $this->repository();
    }

    /**
     * Resolve the repository that builds this controller's index/show queries.
     * Each concrete controller must supply its own repository instance.
     */
    abstract protected function repository(): BaseRepository;

    public function index(): JsonResponse
    {
        $this->authorizeIndex();

        $data = $this->repository->index();
        $rows = $data instanceof LengthAwarePaginator ? $data->getCollection() : $data;
        $meta = [];

        if ($table = $this->repository->table()) {
            // Table mode: rows hold exactly the requested columns, no Resource involved.
            $columns = $table->selected($this->repository->requestedFields());
            $items = $table->rows($rows, $columns);
            $meta['table'] = $table->describe($columns) + [
                'bulk_actions' => array_values(array_map(fn ($action) => $action->describe(), $this->repository->bulkActions())),
            ];
        } else {
            $items = $this->resource::makeCollection($rows, $this->service->getUser(), false, $this->getPlatform());
            $items = $items['data'] ?? $items ?? [];
        }

        if ($data instanceof LengthAwarePaginator) {
            $meta = array_merge($meta, Arr::only($data->toArray(), ['current_page', 'total', 'per_page', 'last_page']));
            $meta['pages_count'] = $meta['last_page'];
        }

        return $this->success($items, meta: $meta);
    }

    /**
     * `POST {resource}/bulk` — apply one of the repository's bulk actions to a
     * selection (`ids`, `filters` or `all`), with optional `extra` data.
     * Answers 404 when the repository declares no bulk actions.
     */
    public function bulk(): JsonResponse
    {
        $runner = new BulkRunner($this->repository);
        abort_if($runner->actions() === [], 404);

        $input = $runner->validate(request()->all());
        $this->authorize($input['action']->ability(), $this->repository->getModel());

        $result = $runner->run($input['action'], $input['selection'], $input['extra']);

        return $this->success(
            $result,
            message: __($result['queued'] ? 'fancy-crud::messages.bulk_queued' : 'fancy-crud::messages.bulk_done')
        );
    }

    /**
     * Overridable so a subresource over a shared model (e.g. Student/
     * Supervisor/Parent, all App\Models\Dashboard\Auth\User) can swap this
     * for a bare permission-string check instead — a Policy keyed on a
     * shared model would gate every account kind through the same check.
     */
    protected function authorizeIndex(): void
    {
        $this->authorize('index', $this->repository->getModel());
    }

    protected function authorizeShow(Model $item): void
    {
        $this->authorize('show', $item);
    }

    public function formatItem(Model $item)
    {
        return $this->resource::makeWithDetail(
            $item,
            $this->service->getUser(),
            true,
            $this->getPlatform()
        );
    }

    public function show(int|string $id): JsonResponse
    {
        $item = $this->repository->find($id);

        if (is_null($item)) {
            return $this->notFound();
        }

        $this->authorizeshow($item);

        return $this->success($this->formatItem($item));
    }

    public function store(): JsonResponse
    {
        $data = app($this->storeRequest)->validated();
        $item = $this->service->store($data);

        return $this->success($this->formatItem($item), 201);
    }

    public function update(int|string $id): JsonResponse
    {
        $data = app($this->updateRequest)->validated();
        $item = $this->service->update($id, $data);

        if (is_null($item)) {
            return $this->notFound();
        }

        return $this->success($this->formatItem($item));
    }

    public function destroy(int|string $id): JsonResponse
    {
        $result = $this->service->destroy($id);

        if (! $result) {
            return $this->notFound();
        }

        return $this->success();
    }

    abstract public function getPlatform(): string;
}

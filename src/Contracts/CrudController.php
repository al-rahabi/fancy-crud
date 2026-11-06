<?php

declare(strict_types=1);

namespace FancyCrud\Contracts;

use FancyCrud\Bulk\BulkRunner;
use FancyCrud\Excel\Contracts\Exportable;
use FancyCrud\Excel\Contracts\Importable;
use FancyCrud\Excel\Traits\ExportTrait;
use FancyCrud\Excel\Traits\ImportTrait;
use FancyCrud\Repositories\BaseRepository;
use FancyCrud\Services\BaseCrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * The only place that talks HTTP, authorizes and runs logic around a write.
 *
 * Every write follows the same short shape — authorize → validated data →
 * `before*` hook → one service call → `after*` hook → respond. Override the
 * hooks (`beforeStore`, `afterStore`, `beforeUpdate`, `afterUpdate`,
 * `beforeDestroy`, `afterDestroy`) for logic around a write, and `storeData()` /
 * `updateData()` to add server-side values such as the acting user. Services and
 * actions stay free of request, user and authorization.
 *
 * Excel support is built in and optional: set `$excel` to a `BaseExcel` class and
 * `export()`, `import()` and `importTemplate()` work; leave it null and they
 * answer 404. Any of those methods (or `exportQuery()`, `createForExcel()`, …)
 * defined on a concrete controller overrides the default.
 *
 * @property BaseCrudService $service
 */
abstract class CrudController extends Controller implements Exportable, Importable
{
    use ExportTrait, ImportTrait;

    protected BaseRepository $repository;

    protected ?string $resource = null;

    protected ?string $storeRequest = null;

    protected ?string $updateRequest = null;

    /** @var class-string<BaseIndexRequest> Validates the index query parameters. */
    protected string $indexRequest = BaseIndexRequest::class;

    /** @var class-string<BaseBulkRequest> Validates the bulk payload. */
    protected string $bulkRequest = BaseBulkRequest::class;

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

    abstract public function getPlatform(): string;

    // ---- read ------------------------------------------------------------

    public function index(): JsonResponse
    {
        $this->authorizeIndex();

        $data = $this->repository->withParameters($this->indexParameters())->index();
        $rows = $data instanceof LengthAwarePaginator ? $data->getCollection() : $data;

        return $this->success($this->indexItems($rows), meta: $this->indexMeta($data));
    }

    public function show(int|string $id): JsonResponse
    {
        $item = $this->repository->find($id);

        if (is_null($item)) {
            return $this->notFound();
        }

        $this->authorizeShow($item);

        return $this->success($this->formatItem($item));
    }

    /**
     * Validated index parameters (`per_page`, `search`, `fields`, `order_by`, filters…).
     *
     * @return array<string, mixed>
     */
    protected function indexParameters(): array
    {
        return app($this->indexRequest)->validated();
    }

    /**
     * Rules of the resource's own index parameters, used by {@see BaseIndexRequest}.
     *
     * @return array<string, mixed>
     */
    public function indexRules(): array
    {
        return $this->repository->indexRules();
    }

    /**
     * Rules of the bulk payload that depend on the resource, used by {@see BaseBulkRequest}.
     *
     * @return array<string, mixed>
     */
    public function bulkRules(mixed $action): array
    {
        return (new BulkRunner($this->repository))->rules(is_string($action) ? $action : null);
    }

    /**
     * @param  Collection<int, Model>  $rows
     */
    protected function indexItems(Collection $rows): mixed
    {
        if ($table = $this->repository->table()) {
            // Table mode: rows hold exactly the requested columns, no Resource involved.
            return $table->rows($rows, $table->selected($this->repository->requestedFields()), $this->user());
        }

        $items = $this->resource::makeCollection($rows, $this->user(), false, $this->getPlatform());

        return $items['data'] ?? $items ?? [];
    }

    /**
     * @param  Collection<int, Model>|LengthAwarePaginator<int, Model>  $data
     * @return array<string, mixed>
     */
    protected function indexMeta(Collection|LengthAwarePaginator $data): array
    {
        $meta = [];

        if ($table = $this->repository->table()) {
            $columns = $table->selected($this->repository->requestedFields());
            $meta['table'] = $table->describe($columns) + [
                'bulk_actions' => array_values(array_map(fn ($action) => $action->describe(), $this->repository->bulkActions())),
            ];
        }

        if ($data instanceof LengthAwarePaginator) {
            $meta = array_merge($meta, Arr::only($data->toArray(), ['current_page', 'total', 'per_page', 'last_page']));
            $meta['pages_count'] = $meta['last_page'];
        }

        return $meta;
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
        return $this->resource::makeWithDetail($item, $this->user(), true, $this->getPlatform());
    }

    // ---- write -----------------------------------------------------------

    public function store(): JsonResponse
    {
        $this->authorize('create', $this->repository->getModel());

        $data = $this->storeData();
        $this->beforeStore($data);
        $item = $this->service->store($data);
        $this->afterStore($item, $data);

        return $this->success($this->formatItem($item), 201);
    }

    public function update(int|string $id): JsonResponse
    {
        $item = $this->service->findItem($id);
        if (is_null($item)) {
            return $this->notFound();
        }
        $this->authorize('update', $item);

        $data = $this->updateData();
        $this->beforeUpdate($item, $data);
        $item = $this->service->update($item, $data) ?? $item;
        $this->afterUpdate($item, $data);

        return $this->success($this->formatItem($item));
    }

    public function destroy(int|string $id): JsonResponse
    {
        $item = $this->service->findItem($id);
        if (is_null($item)) {
            return $this->notFound();
        }
        $this->authorize('delete', $item);

        $this->beforeDestroy($item);
        if (! $this->service->destroy($item)) {
            return $this->error();
        }
        $this->afterDestroy($item);

        return $this->success();
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

        $input = $runner->resolve(app($this->bulkRequest)->validated());
        $this->authorize($input['action']->ability(), $this->repository->getModel());

        $result = $runner->run($input['action'], $input['selection'], $input['extra']);

        return $this->success(
            $result,
            message: __($result['queued'] ? 'fancy-crud::messages.bulk_queued' : 'fancy-crud::messages.bulk_done')
        );
    }

    // ---- data & hooks (override in the concrete controller) ----------------

    /**
     * The validated store payload. Add server-side values here, e.g.
     * `[...parent::storeData(), 'created_by' => $this->user()?->getAuthIdentifier()]`.
     *
     * @return array<string, mixed>
     */
    protected function storeData(): array
    {
        return app($this->storeRequest)->validated();
    }

    /**
     * @return array<string, mixed>
     */
    protected function updateData(): array
    {
        return app($this->updateRequest)->validated();
    }

    /** @param  array<string, mixed>  $data */
    protected function beforeStore(array $data): void {}

    /** @param  array<string, mixed>  $data */
    protected function afterStore(Model $item, array $data): void {}

    /** @param  array<string, mixed>  $data */
    protected function beforeUpdate(Model $item, array $data): void {}

    /** @param  array<string, mixed>  $data */
    protected function afterUpdate(Model $item, array $data): void {}

    protected function beforeDestroy(Model $item): void {}

    protected function afterDestroy(Model $item): void {}
}

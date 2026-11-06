<?php

namespace FancyCrud\Bulk;

use FancyCrud\Repositories\BaseRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validates a bulk request and runs the chosen {@see BulkAction} over the
 * selection. HTTP-free apart from the validation exception.
 *
 * Selection (exactly one): `['ids' => [...]]`, `['filters' => [...]]` or `['all' => true]`.
 */
class BulkRunner
{
    public function __construct(protected BaseRepository $repository) {}

    /**
     * @return array<string, BulkAction>
     */
    public function actions(): array
    {
        return $this->repository->bulkActions();
    }

    /**
     * Validate the request payload.
     *
     * @return array{action: BulkAction, selection: array, extra: array}
     *
     * @throws ValidationException
     */
    public function validate(array $input): array
    {
        $actions = $this->actions();

        Validator::make($input, [
            'action' => ['required', 'string', 'in:'.implode(',', array_keys($actions))],
            'ids' => ['nullable', 'array', 'min:1'],
            'ids.*' => ['required'],
            'filters' => ['nullable', 'array'],
            'all' => ['nullable', 'boolean'],
            'extra' => ['nullable', 'array'],
        ])->validate();

        $action = $actions[$input['action']];
        $selection = $this->selection($input);

        $rules = [];
        foreach ($action->rules() as $key => $rule) {
            $rules["extra.$key"] = $rule;
        }
        $extra = [];
        if ($rules !== []) {
            $extra = Arr::get(Validator::make($input, $rules)->validate(), 'extra', []);
        }

        return ['action' => $action, 'selection' => $selection, 'extra' => $extra];
    }

    /**
     * Normalize the selection part of the payload. An empty `filters` object is
     * rejected rather than read as "everything".
     *
     * @throws ValidationException
     */
    protected function selection(array $input): array
    {
        $ids = ! empty($input['ids']);
        $filters = Arr::only($input['filters'] ?? [], $this->repository->resolvedFilters()->names());
        $filters = array_filter($filters, fn ($value) => ! is_null($value));
        $all = filter_var($input['all'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! empty($input['filters']) && $filters === [] && ! $ids && ! $all) {
            throw ValidationException::withMessages(['filters' => __('fancy-crud::messages.bulk_filters_invalid')]);
        }

        $chosen = array_filter(['ids' => $ids, 'filters' => $filters !== [], 'all' => $all]);
        if (count($chosen) !== 1) {
            throw ValidationException::withMessages(['selection' => __('fancy-crud::messages.bulk_selection_invalid')]);
        }

        return match (array_key_first($chosen)) {
            'ids' => ['ids' => array_values($input['ids'])],
            'filters' => ['filters' => $filters],
            default => ['all' => true],
        };
    }

    /**
     * Run the action. `job` actions are queued and report `processed => null`.
     *
     * @return array{mode: string, queued: bool, processed: int|null}
     */
    public function run(BulkAction $action, array $selection, array $extra = []): array
    {
        $mode = $action->mode();

        if ($mode === BulkAction::MODE_JOB) {
            $job = new BulkActionJob(get_class($this->repository), get_class($action), $selection, $extra);
            $job->onConnection($action->connection())->onQueue($action->queue());
            dispatch($job);

            return ['mode' => $mode, 'queued' => true, 'processed' => null];
        }

        return ['mode' => $mode, 'queued' => false, 'processed' => $this->process($action, $selection, $extra)];
    }

    /**
     * Execute the action now; used by the request (query/sync) and by the job.
     */
    public function process(BulkAction $action, array $selection, array $extra = []): int
    {
        $query = $this->repository->bulkQuery($selection);

        if ($action->mode() === BulkAction::MODE_QUERY) {
            return (int) $action->byQuery($query, $extra);
        }

        $handler = $action->mode() === BulkAction::MODE_SYNC ? 'bySync' : 'byJob';
        $count = 0;
        $query->chunkById($action->chunkSize(), function ($models) use ($action, $extra, $handler, &$count): void {
            foreach ($models as $model) {
                $action->{$handler}($model, $extra);
                $count++;
            }
        });

        return $count;
    }
}

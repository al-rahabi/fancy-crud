<?php

declare(strict_types=1);

namespace FancyCrud\Bulk;

use FancyCrud\Repositories\BaseRepository;

/**
 * Resolves a *validated* bulk payload and runs the chosen {@see BulkAction} over
 * the selection. It reads no request: validation is
 * {@see \FancyCrud\Contracts\BaseBulkRequest}'s job.
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
     * Rules for the bulk request: the action must be one of the repository's,
     * `filters.<name>` use each filter's rules and `extra.<key>` the action's rules.
     *
     * @return array<string, mixed>
     */
    public function rules(?string $actionName): array
    {
        $actions = $this->actions();
        $rules = ['action' => ['required', 'string', 'in:'.implode(',', array_keys($actions))]];

        foreach ($this->repository->resolvedFilters()->all() as $filter) {
            $rules['filters.'.$filter->name()] = $filter->getRules();
        }

        foreach (($actions[$actionName] ?? null)?->rules() ?? [] as $key => $rule) {
            $rules["extra.$key"] = $rule;
        }

        return $rules;
    }

    /**
     * Turn validated data into the action, the selection and the extra data.
     *
     * @param  array<string, mixed>  $validated
     * @return array{action: BulkAction, selection: array<string, mixed>, extra: array<string, mixed>}
     */
    public function resolve(array $validated): array
    {
        return [
            'action' => $this->actions()[$validated['action']],
            'selection' => $this->selection($validated),
            'extra' => $validated['extra'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function selection(array $validated): array
    {
        if (! empty($validated['ids'])) {
            return ['ids' => array_values($validated['ids'])];
        }

        $filters = array_filter($validated['filters'] ?? [], fn (mixed $value): bool => ! is_null($value));
        if ($filters !== []) {
            return ['filters' => $filters];
        }

        return ['all' => true];
    }

    /**
     * Run the action. `job` actions are queued and report `processed => null`.
     *
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>  $extra
     * @return array{mode: string, queued: bool, processed: int|null}
     */
    public function run(BulkAction $action, array $selection, array $extra = []): array
    {
        $mode = $action->mode();

        if ($mode === BulkAction::MODE_JOB) {
            $this->dispatch($action, $selection, $extra);

            return ['mode' => $mode, 'queued' => true, 'processed' => null];
        }

        return ['mode' => $mode, 'queued' => false, 'processed' => $this->process($action, $selection, $extra)];
    }

    /**
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>  $extra
     */
    protected function dispatch(BulkAction $action, array $selection, array $extra): void
    {
        $job = new BulkActionJob(get_class($this->repository), get_class($action), $selection, $extra);
        $job->onConnection($action->connection())->onQueue($action->queue());
        dispatch($job);
    }

    /**
     * Execute the action now; used by the request (query/sync) and by the job.
     *
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>  $extra
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

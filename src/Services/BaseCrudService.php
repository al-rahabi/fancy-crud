<?php

declare(strict_types=1);

namespace FancyCrud\Services;

use FancyCrud\Actions\Crud\CreateAction;
use FancyCrud\Actions\Crud\DestroyAction;
use FancyCrud\Actions\Crud\UpdateAction;
use FancyCrud\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Owns the write side of a resource: create / update / delete from plain typed
 * data. No request, no authenticated user, no authorization and no before/after
 * hooks — the controller authorizes and runs everything around the call.
 * Reading (index/show) is the repository's job — see {@see BaseRepository}.
 */
abstract class BaseCrudService extends BaseService
{
    /** @var class-string<Model> */
    protected string $model = Model::class;

    /**
     * Resolve a single record for update and destroy. Relations are the
     * repository's concern, so this is a bare lookup.
     */
    public function findItem(int|string|Model $id): ?Model
    {
        if ($id instanceof Model) {
            return $id;
        }

        return $this->globalQuery()->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Model
    {
        return (new CreateAction($this, $data))->execute();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int|string|Model $idOrModel, array $data): ?Model
    {
        return (new UpdateAction($this, $data))->for($idOrModel)->execute();
    }

    public function destroy(int|string|Model $idOrModel): bool
    {
        return (new DestroyAction($this))->for($idOrModel)->execute();
    }

    /**
     * @return class-string<Model>
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Start a fresh model query for the write operations.
     */
    public function globalQuery(): Builder
    {
        return $this->model::query();
    }

    /**
     * Attributes derived from the data itself on store (e.g. a slug). Must be
     * pure: no request, no user, no side effects.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function storeAdditionalData(array $data): array
    {
        return [];
    }

    /**
     * Attributes derived from the data itself on update. Same rules as store.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateAdditionalData(array $data): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createMethod(array $data): Model
    {
        return $this->globalQuery()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateMethod(Model $item, array $data): Model
    {
        $item->update($data);

        return $item;
    }
}

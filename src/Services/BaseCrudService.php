<?php

namespace FancyCrud\Services;

use FancyCrud\Actions\Crud\CreateAction;
use FancyCrud\Actions\Crud\DestroyAction;
use FancyCrud\Actions\Crud\UpdateAction;
use FancyCrud\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use FancyCrud\Support\PermissionName;

/**
 * Owns the write side of a resource: create/update/delete orchestration and the
 * hooks around it. Reading (index/show) is the repository's job — see
 * {@see BaseRepository}.
 */
abstract class BaseCrudService extends BaseService
{
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
     * Store a new item.
     */
    public function store(array $data): Model
    {
        return (new CreateAction($this, $data))->execute();
    }

    /**
     * Update an item.
     */
    public function update(int|string|Model $idOrModel, array $data): ?Model
    {
        return (new UpdateAction($this, $data))->for($idOrModel)->execute();
    }

    /**
     * Delete an item.
     */
    public function destroy(int|string|Model $idOrModel): bool
    {
        return (new DestroyAction($this))->for($idOrModel)->execute();
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the permission name for a specific action.
     */
    public function getPermissionName(string $action): string
    {
        return PermissionName::for($this->getModel(), $action);
    }

    /**
     * Start a fresh model query for the write operations.
     */
    public function globalQuery(): Builder
    {
        return $this->model::query();
    }

    /**
     * Inject additional data during store.
     */
    public function storeAdditionalData(array $data): array
    {
        return [];
    }

    /**
     * Inject additional data during update.
     */
    public function updateAdditionalData(array $data): array
    {
        return [];
    }

    /**
     * After save hook (called during both create and update).
     */
    public function afterSave(Model $model, array $data): void {}

    public function beforeStore(array $data): void
    {
        $this->beforeSave($data);
    }

    public function beforeUpdate(array $data): void
    {
        $this->beforeSave($data);
    }

    public function beforeSave(array $data): void {}

    /**
     * After store hook.
     */
    public function afterStore(Model $model, array $data): void
    {
        $this->afterSave($model, $data);
    }

    /**
     * After update hook.
     */
    public function afterUpdate(Model $model, array $data): void
    {
        $this->afterSave($model, $data);
    }

    /**
     * Before delete hook.
     */
    public function beforeDelete(Model $item): void {}

    /**
     * After delete hook.
     */
    public function afterDelete(): void {}

    public function createMethod(array $data): Model
    {
        return $this->globalQuery()->create($data);
    }

    public function updateMethod(Model $item, array $data): Model
    {
        $item->update($data);

        return $item;
    }
}

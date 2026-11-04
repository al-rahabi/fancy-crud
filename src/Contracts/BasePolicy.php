<?php

namespace FancyCrud\Contracts;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BasePolicy
{
    use HandlesAuthorization;

    protected string $resource;

    public function __construct()
    {
        if (! isset($this->resource)) {
            $modelClass = class_basename(static::class);
            $modelClass = str_replace('Policy', '', $modelClass);
            $modelClass = Str::plural(Str::snake($modelClass, ' '));
            $this->resource = $modelClass;
        }
    }

    public function index(Authenticatable $user): bool
    {
        return $this->hasPermission('index', $user);
    }

    public function show(Authenticatable $user, ?Model $model = null): bool
    {
        return $this->hasPermission('show', $user) && (is_null($model) || $this->hasAbility('show', $user, $model));
    }

    public function create(Authenticatable $user): bool
    {
        return $this->hasPermission('create', $user);
    }

    public function update(Authenticatable $user, ?Model $model = null): bool
    {
        return $this->hasPermission('update', $user) && (is_null($model) || $this->hasAbility('update', $user, $model));
    }

    public function delete(Authenticatable $user, ?Model $model = null): bool
    {
        return $this->hasPermission('delete', $user) && (is_null($model) || $this->hasAbility('delete', $user, $model));
    }

    public function export(Authenticatable $user): bool
    {
        return $this->hasPermission('export', $user);
    }

    public function import(Authenticatable $user): bool
    {
        return $this->hasPermission('import', $user);
    }

    public function hasAbility(string $action, Authenticatable $user, ?Model $model = null): bool
    {
        $method = $this->getAbilityMethodName($action);
        if ($model && method_exists($model, $method)) {
            return $model->{$method}($user);
        }

        return true;
    }

    private function getAbilityMethodName(string $action): string
    {
        return 'can'.ucfirst($action);
    }

    protected function hasPermission(string $action, Authenticatable $user, ?Model $model = null): bool
    {
        return $user->can("{$action} {$this->resource}");
    }
}

<?php

declare(strict_types=1);

namespace FancyCrud\Testing;

use FancyCrud\Permissions\PermissionSyncer;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

/**
 * Helper for `crudUser()` implementations.
 *
 * ```php
 * protected function crudUser(?array $permissions = null): Authenticatable
 * {
 *     return $this->grantPermissions(User::factory()->create(), $permissions, 'web');
 * }
 * ```
 *
 * `null` grants every permission configured for the guard; an array grants
 * exactly those names. The guard defaults to the user's own guard.
 */
trait InteractsWithPermissions
{
    protected function grantPermissions(Model $user, ?array $permissions = null, ?string $guard = null): Model
    {
        $guard ??= method_exists($user, 'getDefaultGuardName')
            ? $user->getDefaultGuardName()
            : config('auth.defaults.guard');

        $permissionClass = app(PermissionRegistrar::class)->getPermissionClass();

        if ($permissions === null) {
            app(PermissionSyncer::class)->syncGuard($guard, config("fancy-crud.permissions.guards.{$guard}", []));
            $permissions = $permissionClass::query()->where('guard_name', $guard)->pluck('name')->all();
        } else {
            foreach ($permissions as $name) {
                $permissionClass::findOrCreate($name, $guard);
            }
        }

        $user->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}

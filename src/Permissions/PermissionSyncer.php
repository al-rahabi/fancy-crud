<?php

declare(strict_types=1);

namespace FancyCrud\Permissions;

use FancyCrud\Contracts\BasePolicy;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Keeps the database permissions of each guard identical to the definition in
 * config: the "{action} {resource}" permissions {@see BasePolicy}
 * checks, one set per guard.
 *
 * Syncing a guard:
 *  1. create-or-updates every permission and collects their ids,
 *  2. deletes every other permission of that guard (ids not collected),
 *  3. gives the guard's main role ALL of those permissions,
 *  4. creates the extra configured roles.
 *
 * ```php
 * $syncer = app(PermissionSyncer::class);
 * $syncer->sync();                         // every guard in fancy-crud.permissions.guards
 * $syncer->syncGuard('admin', [
 *     'resources' => ['users', 'orders' => ['index', 'cancel']],
 *     'main_role' => 'super admin',        // optional, default fancy-crud.permissions.main_role; false = none
 * ]);
 * ```
 */
class PermissionSyncer
{
    /**
     * Sync all configured guards (or only `$guards`).
     *
     * @param  array<string, array>|null  $definitions  guard => definition; defaults to config
     * @param  array<int, string>|null  $guards  restrict to these guard names
     * @return array<string, Collection> permissions created/updated, keyed by guard
     */
    public function sync(?array $definitions = null, ?array $guards = null): array
    {
        $definitions ??= config('fancy-crud.permissions.guards', []);
        $result = [];

        foreach ($definitions as $guard => $definition) {
            if ($guards !== null && ! in_array($guard, $guards, true)) {
                continue;
            }

            $result[$guard] = $this->syncGuard($guard, $definition);
        }

        $this->forgetCache();

        return $result;
    }

    /**
     * @param  array{resources?: array, actions?: array, roles?: array, main_role?: string|false|null}  $definition
     * @return Collection<int, Permission> the guard's permissions after the sync
     */
    public function syncGuard(string $guard, array $definition): Collection
    {
        $registrar = app(PermissionRegistrar::class);
        $permissionClass = $registrar->getPermissionClass();
        $roleClass = $registrar->getRoleClass();

        $defaults = $definition['actions'] ?? config('fancy-crud.permissions.default_actions', []);
        $permissions = collect();

        // 1. create or update, remembering the ids
        foreach ($definition['resources'] ?? [] as $resource => $actions) {
            if (is_int($resource)) { // ['posts', 'orders'] shorthand
                [$resource, $actions] = [$actions, null];
            }

            foreach ($actions ?? $defaults as $action) {
                $permissions->push($permissionClass::query()->updateOrCreate([
                    'name' => trim("{$action} {$resource}"),
                    'guard_name' => $guard,
                ]));
            }
        }

        $permissions = $permissions->unique(fn ($permission) => $permission->getKey())->values();

        // 2. delete the guard's permissions that are not in the definition any more
        $permissionClass::query()
            ->where('guard_name', $guard)
            ->whereNotIn((new $permissionClass)->getKeyName(), $permissions->map->getKey()->all())
            ->get()
            ->each->delete();

        // 3. the guard's main role holds every permission of the guard
        $mainRole = array_key_exists('main_role', $definition)
            ? $definition['main_role']
            : config('fancy-crud.permissions.main_role');

        if ($mainRole) {
            $roleClass::findOrCreate($mainRole, $guard)->syncPermissions($permissions);
        }

        // 4. extra roles hold exactly their configured permissions (removed ones are revoked)
        foreach ($definition['roles'] ?? [] as $name => $granted) {
            $roleClass::findOrCreate($name, $guard)->syncPermissions(
                $granted === ['*'] || $granted === '*'
                    ? $permissions
                    : collect((array) $granted)->map(
                        fn (string $permission) => $permissionClass::findOrCreate($permission, $guard)
                    )
            );
        }

        return $permissions;
    }

    public function forgetCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

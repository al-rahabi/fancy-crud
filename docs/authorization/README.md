# authorization — Policies, permissions, Spatie sync

Source: `src/Contracts/BasePolicy.php`, `src/Permissions/PermissionSyncer.php`, `src/Console/SyncPermissionsCommand.php`,
`config/fancy-crud.php` (`permissions` key). Spatie `laravel-permission` ^6|^7 is a **required** dependency.

## Permission naming convention
`"{action} {resource}"`, resource = plural snake-case of the model/policy name with **spaces**:
`Post` → `index posts`, `BlogPost` → `create blog posts`. Standard actions: `index show create update delete export import`.
Custom abilities (e.g. `cancel`, `send`) are just extra actions declared per resource.

Both sides use `FancyCrud\Support\PermissionName` (`resource($classOrModel)`, `for($classOrModel, $action)`): `BasePolicy::$resource` (policy class name minus `Policy`, or an explicit `protected string $resource`).

## `BasePolicy`
Methods `index, show, create, update, delete, export, import` each call `hasPermission($action, $user)` → `$user->can("{action} {resource}")`.
`show/update/delete` additionally call `hasAbility($action, $user, $model)`: if the model defines `canShow/canUpdate/canDelete($user)`
that must return true. Register in a service provider: `Gate::policy(Post::class, PostPolicy::class)` (or rely on auto-discovery).
A minimal policy is `class PostPolicy extends BasePolicy {}`. Add custom abilities as public methods calling `$this->hasPermission('cancel', $user)`.

## Where checks happen
- Reads/Excel: Gate → policy (`CrudController::authorizeIndex/authorizeShow`, `ExcelHeadersTrait::authorizeExcel`).
- Writes and bulk: the controller's `authorize('create'|'update'|'delete'|$bulkAbility, …)` → policy → 403. Services and actions never authorize.
With Spatie, `$user->can('index posts')` resolves through the Gate permission check Spatie registers.

## `PermissionSyncer` (multi-guard)
Keeps the DB permissions of each guard identical to the config definition. `syncGuard()` does, in order:
1. **create-or-update** every permission (`updateOrCreate(name, guard_name)`) and collect their ids;
2. **delete** every permission of that guard whose id is not in the collected ids (other guards are untouched);
3. give the guard's **main role** (default name `fancy-crud.permissions.main_role` = `admin`; per guard `main_role`, `false` disables)
   *all* those permissions (`syncPermissions`);
4. create the extra `roles` (`'*'` = every permission of the guard, otherwise a list of names).

```php
$syncer = app(\FancyCrud\Permissions\PermissionSyncer::class);
$syncer->sync();                                  // all guards in config, returns [guard => Collection<Permission>]
$syncer->sync(null, ['admin']);                   // only some guards
$syncer->syncGuard('web', ['resources' => ['posts'], 'main_role' => 'super admin']);
$syncer->forgetCache();
```
- `resources`: `'posts'` (list shorthand) → actions from the guard's `actions` else `default_actions`; or `'orders' => ['index','cancel']`.
- Same permission name may exist in several guards. Uses Spatie's configured permission/role models.
- Idempotent; permission ids stay stable across runs. **Warning:** a guard whose definition has no resources loses all its permissions.

### Config shape (`config/fancy-crud.php`)
```php
'permissions' => [
    'main_role' => 'admin',
    'default_actions' => ['index','show','create','update','delete','export','import'],
    'guards' => [
        'web'   => ['resources' => ['posts', 'orders' => ['index','cancel']],
                    'roles' => ['editor' => ['index posts']]],
        'admin' => ['resources' => ['users'], 'actions' => ['index','show'], 'main_role' => 'root'],
    ],
],
```

## Command
`php artisan fancy-crud:permissions` — all guards; `--guard=web --guard=admin` to restrict. Prints `[guard] synced N permissions.` (warns if `guards` is empty). Obsolete permissions of the synced guards are deleted. Registered only when running in console.

## Not included (host-app concerns)
Tenant/company-scoped roles, per-branch rules, subscription gating — the package only covers plain Spatie permissions.

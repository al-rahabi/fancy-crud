<?php

use FancyCrud\Permissions\PermissionSyncer;
use FancyCrud\Testing\InteractsWithPermissions;
use FancyCrud\Tests\Unit\Fixtures\PermUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['fancy-crud.permissions' => [
        'main_role' => 'admin',
        'default_actions' => ['index', 'show'],
        'guards' => [
            'web' => ['resources' => ['posts', 'orders' => ['index', 'cancel']], 'roles' => ['editor' => ['index posts']]],
            'api' => ['resources' => ['posts'], 'actions' => ['index'], 'main_role' => 'root'],
        ],
    ]]);
});

function names(string $guard): array
{
    return Permission::where('guard_name', $guard)->orderBy('name')->pluck('name')->all();
}

it('creates permissions per guard, independent of each other', function () {
    $result = app(PermissionSyncer::class)->sync();

    expect(array_keys($result))->toBe(['web', 'api'])
        ->and(names('web'))->toBe(['cancel orders', 'index orders', 'index posts', 'show posts'])
        ->and(names('api'))->toBe(['index posts']);
});

it('gives each guard a main role holding all of its permissions', function () {
    app(PermissionSyncer::class)->sync();

    $admin = Role::findByName('admin', 'web');
    $root = Role::findByName('root', 'api');

    expect($admin->permissions->pluck('name')->sort()->values()->all())->toBe(names('web'))
        ->and($root->permissions->pluck('name')->all())->toBe(['index posts'])
        ->and(Role::findByName('editor', 'web')->permissions->pluck('name')->all())->toBe(['index posts']);
});

it('is idempotent and keeps permission ids stable', function () {
    $syncer = app(PermissionSyncer::class);
    $first = $syncer->sync()['web']->map->getKey()->all();
    $second = $syncer->sync()['web']->map->getKey()->all();

    expect($second)->toBe($first)->and(Permission::count())->toBe(5);
});

it('deletes permissions of the guard that are no longer defined', function () {
    $syncer = app(PermissionSyncer::class);
    $syncer->sync();
    Permission::create(['name' => 'old thing', 'guard_name' => 'web']);
    Permission::create(['name' => 'other guard thing', 'guard_name' => 'other']);

    $syncer->syncGuard('web', ['resources' => ['posts'], 'actions' => ['index']]);

    expect(names('web'))->toBe(['index posts'])
        ->and(names('other'))->toBe(['other guard thing']) // untouched guard
        ->and(Role::findByName('admin', 'web')->permissions->pluck('name')->all())->toBe(['index posts']);
});

it('can disable the main role for a guard', function () {
    app(PermissionSyncer::class)->syncGuard('web', ['resources' => ['posts'], 'main_role' => false]);

    expect(Role::where('guard_name', 'web')->count())->toBe(0);
});

it('syncs through the artisan command, optionally for chosen guards', function () {
    $this->artisan('fancy-crud:permissions', ['--guard' => ['api']])->assertSuccessful();

    expect(names('api'))->toBe(['index posts'])->and(names('web'))->toBe([]);

    $this->artisan('fancy-crud:permissions')->assertSuccessful();
    expect(names('web'))->not->toBe([]);
});

it('grants permissions to a test user', function () {
    $helper = new class
    {
        use InteractsWithPermissions;

        public function grant($user, $permissions = null, $guard = null)
        {
            return $this->grantPermissions($user, $permissions, $guard);
        }
    };

    $limited = $helper->grant(PermUser::create(['name' => 'l']), ['index posts'], 'web');
    expect($limited->hasPermissionTo('index posts'))->toBeTrue()
        ->and($limited->getAllPermissions()->count())->toBe(1);

    $full = $helper->grant(PermUser::create(['name' => 'f']), null, 'web');
    expect($full->getAllPermissions()->pluck('name')->sort()->values()->all())->toBe(names('web'));
});

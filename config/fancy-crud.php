<?php

return [
    /*
    | Filesystem disk used by assetUrl() / MediaCast to build public URLs.
    */
    'media_disk' => env('FANCY_CRUD_MEDIA_DISK', 'public'),

    /*
    | Locales accepted by AcceptedLanguagesMiddleware. null = use config('app.supported_locales'),
    | else just config('app.locale').
    */
    'supported_locales' => null,

    /*
    | Permissions (spatie/laravel-permission). BasePolicy checks "{action} {resource}",
    | e.g. "index posts"; `php artisan fancy-crud:permissions` creates them per guard.
    |
    | Every guard has its own permissions and roles:
    |
    |  'guards' => [
    |      'web'   => [
    |          'resources' => ['posts', 'orders' => ['index', 'cancel']],
    |          'roles'     => ['editor' => ['index posts']],
    |      ],
    |      'admin' => ['resources' => ['users'], 'actions' => ['index', 'show']],
    |  ],
    |
    |  - resources: 'posts' (uses the guard's `actions`, else `default_actions`)
    |               or 'orders' => ['index', 'cancel']
    |  - actions:   optional per-guard override of `default_actions`
    |  - roles:     extra roles: 'name' => ['index posts', ...] (a '*' list = every permission of the guard)
    |  - main_role: per-guard name of its main role (default: `main_role` below); false disables it
    |
    | Syncing a guard (1) create-or-updates each permission, (2) DELETES every permission of that
    | guard whose id was not just created/updated, (3) gives the guard's main role ALL its permissions,
    | (4) syncs each extra role to exactly its listed permissions (removed ones are revoked).
    */
    'permissions' => [
        'main_role' => 'admin', // every guard gets this role holding all of its permissions
        'default_actions' => ['index', 'show', 'create', 'update', 'delete', 'export', 'import'],
        'guards' => [],
    ],
];

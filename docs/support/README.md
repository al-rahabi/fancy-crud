# support — Traits, Casts, Enums, Helpers, Notification, Config

## Model traits (`src/Traits/`)
| Trait | Provides |
|-------|----------|
| `Activable` | `$activeColumn = 'is_active'`, `activeColumnName()`, `scopeActive($active = true)`, `toggleActive()` (flips the current value) |
| `Sortable` | `$sortingColumn = 'sort_index'`, `sortingColumnName()`, `scopeSorted($direction = 'asc')` |
| `HasSlug` | Slug generation on `creating`/`updating`. Model defines `sluggable(): array` of `['col' => 'title'\|Closure, 'slug_col' => 'slug', 'separator' => '_', 'unique_cond' => fn($slug) => bool, 'force_slug' => fn($item) => ?string]`; set `$shouldBeUnique = true` on the model to suffix `separator + id + n` until unique. Arabic text is transliterated with `Str::ascii`. Scope `bySlug($slug)` |
| `Makeable` | `static make(...$args)` → `new static(...)` (used by the Excel managers) |
| `ResponseTrait`, `PaginationMetaTrait` | see [http](../http/README.md) |

## `BaseEnumTrait` (`Contracts/`)
For backed enums: `toArray()` (`value => name`), `toValues()`, `toRule()` (`Rule::in(values)`). Used by the Excel enums.

## `MediaCast` (`Casts/`)
Eloquent cast for file paths stored on the disk named by `fancy-crud.media_disk` (default `public`).
- get: JSON or plain string → `{url, path, size (KB), extension, mime_type}` (array of those for lists); null if empty. `url` from `assetUrl()`.
- set: string → JSON-encoded string; array of paths/`{path}` items → JSON list keeping only files that exist on the media disk (plain strings are stored as given); empty → null.

## `PermissionName` (`src/Support/`)
`PermissionName::resource($classOrModel)` → `blog posts`; `PermissionName::for($classOrModel, 'update')` → `update blog posts`. Single source for the names used by services and policies.

## Helpers
`assetUrl(?string $path): ?string` (`src/helpers.php`) — `Storage::disk(config('fancy-crud.media_disk','public'))->url($path)`; define your own
`assetUrl()` before the package loads to override (tenant-aware storage).
`crudTest()` — see [testing](../testing/README.md).

## `BaseNotification` (`Contracts/`)
Fluent `Notification` for in-app + FCM push: `title()`, `messageEn()`, `messageAr()`, `resource()`, `resourceId()`, `type()`, `data()`.
Channels: always `database`; adds `FcmChannel` when `$notifiable->routeNotificationFor('fcm')` is non-empty. `toDatabase()` returns the data
array; `toFcm()` builds an `FcmMessage`. Requires `laravel-notification-channels/fcm` (a required dependency). The constructor's `$data` seeds the payload.

## Config (`config/fancy-crud.php`, publish tag `fancy-crud-config`)
- `media_disk` (env `FANCY_CRUD_MEDIA_DISK`, default `public`)
- `supported_locales` (null → falls back to app config)
- `permissions.main_role`
- `permissions.default_actions`, `permissions.guards` — see [authorization](../authorization/README.md)

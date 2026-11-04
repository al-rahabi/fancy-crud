# FancyCrud

Abstract building blocks for well-structured Laravel projects: base controllers, services, repositories, CRUD actions, policies, requests, resources, enums, casts and traits.

## Install

```bash
composer require fancycrud/fancy-crud
php artisan vendor:publish --tag=fancy-crud-config
```

## Structure

| Namespace | Contents |
|---|---|
| `FancyCrud\Actions\Crud` | `AbstractAction`, `CreateAction`, `UpdateAction`, `DestroyAction` |
| `FancyCrud\Services` | `BaseService`, `BaseCrudService` |
| `FancyCrud\Repositories` | `BaseRepository` |
| `FancyCrud\Contracts` | `Controller`, `CrudController`, `BasePolicy`, `BaseRequest`, `BaseResource`, `BaseEnumTrait`, `BaseNotification` |
| `FancyCrud\Casts` | `MediaCast` |
| `FancyCrud\Traits` | `Activable`, `HasSlug`, `Sortable`, `ResponseTrait`, `PaginationMetaTrait` |
| `FancyCrud\Middleware` | `AcceptedLanguagesMiddleware` |

`BaseNotification` needs `laravel-notification-channels/fcm`.

## Testing

```bash
composer install && composer test
```
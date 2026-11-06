<?php

use FancyCrud\Casts\MediaCast;
use FancyCrud\Contracts\BaseNotification;
use FancyCrud\Middleware\AcceptedLanguagesMiddleware;
use FancyCrud\Support\PermissionName;
use FancyCrud\Tests\Unit\Fixtures\BlogPost;
use FancyCrud\Tests\Unit\Fixtures\BlogPostPolicy;
use FancyCrud\Tests\Unit\Fixtures\BlogPostService;
use FancyCrud\Tests\Unit\Fixtures\Toggle;
use FancyCrud\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

it('toggles the active flag both ways', function () {
    $toggle = Toggle::create(['is_active' => false]);

    $toggle->toggleActive();
    expect($toggle->fresh()->is_active)->toBeTrue();

    $toggle->toggleActive();
    expect($toggle->fresh()->is_active)->toBeFalse();
});

it('derives the permission name the same way for policies and PermissionName', function () {
    expect(PermissionName::for(BlogPost::class, 'update'))->toBe('update blog posts')
        ->and(PermissionName::for(new BlogPost, 'index'))->toBe('index blog posts')
        ->and((new BlogPostPolicy)->resourceName())->toBe('blog posts');
});

it('keeps services free of users, authorization and hooks', function () {
    $service = new BlogPostService;

    foreach (['getUser', 'getPermissionName', 'beforeStore', 'afterStore', 'beforeUpdate', 'afterUpdate', 'beforeDelete', 'afterDelete', 'beforeSave', 'afterSave'] as $method) {
        expect(method_exists($service, $method))->toBeFalse("$method should not exist on a service");
    }
    expect(method_exists(FancyCrud\Actions\Crud\AbstractAction::class, 'authorize'))->toBeFalse();
});

it('uses the fancy-crud message namespace for response messages', function () {
    $controller = new class
    {
        use ResponseTrait;
    };

    expect($controller->success()->getData()->message)->toBe('Success')
        ->and($controller->notFound()->getData()->message)->toBe('Not found.');

    App::setLocale('ar');
    expect($controller->success()->getData()->message)->toBe('تمت العملية بنجاح');
});

it('falls back to the app locale when no supported locales are configured', function () {
    config(['app.supported_locales' => null, 'fancy-crud.supported_locales' => null, 'app.locale' => 'en']);

    $request = Request::create('/x', 'GET', server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_ACCEPT_LANGUAGE' => 'fr']);
    (new AcceptedLanguagesMiddleware)->handle($request, fn () => null);
    expect(App::getLocale())->toBe('en');

    config(['fancy-crud.supported_locales' => ['en', 'fr']]);
    (new AcceptedLanguagesMiddleware)->handle($request, fn () => null);
    expect(App::getLocale())->toBe('fr');
});

it('keeps the data given to a notification', function () {
    $notification = (new BaseNotification(['foo' => 'bar']))->title('Hi');

    expect($notification->toDatabase())->toMatchArray(['foo' => 'bar', 'title' => 'Hi']);
});

it('checks media on the configured disk', function () {
    config(['fancy-crud.media_disk' => 'public']);
    Storage::fake('public');
    Storage::disk('public')->put('a/one.png', 'x');

    $model = new Toggle;
    $cast = new MediaCast;

    expect(json_decode($cast->set($model, 'f', ['a/one.png', 'a/missing.png', ['path' => 'a/one.png']], []), true))
        ->toBe(['a/one.png', 'a/one.png'])
        ->and($cast->set($model, 'f', 'whatever.png', []))->toBe('"whatever.png"');

    $media = $cast->get($model, 'f', '"a/one.png"', []);
    expect($media)->toMatchArray(['path' => 'a/one.png', 'extension' => 'png', 'mime_type' => 'image/png']);
});

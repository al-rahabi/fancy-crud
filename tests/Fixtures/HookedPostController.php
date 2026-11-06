<?php

declare(strict_types=1);

namespace FancyCrud\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** Records every hook, and adds a server-side value to the payload like an acting-user id would be. */
class HookedPostController extends PostController
{
    /** @var array<int, string> */
    public static array $log = [];

    protected function storeData(): array
    {
        return [...parent::storeData(), 'status' => 'by-user-'.$this->user()?->getAuthIdentifier()];
    }

    protected function beforeStore(array $data): void
    {
        self::$log[] = 'beforeStore:'.$data['title'];
    }

    protected function afterStore(Model $item, array $data): void
    {
        self::$log[] = 'afterStore:'.$item->getKey();
    }

    protected function beforeUpdate(Model $item, array $data): void
    {
        self::$log[] = 'beforeUpdate:'.$item->title;
    }

    protected function afterUpdate(Model $item, array $data): void
    {
        self::$log[] = 'afterUpdate:'.$item->title;
    }

    protected function beforeDestroy(Model $item): void
    {
        self::$log[] = 'beforeDestroy:'.$item->getKey();
    }

    protected function afterDestroy(Model $item): void
    {
        self::$log[] = 'afterDestroy:'.$item->getKey();
    }
}

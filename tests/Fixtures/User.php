<?php

namespace FancyCrud\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** In-memory user: `can('index posts')` is answered from a permission list. */
class User extends Authenticatable
{
    protected $guarded = [];

    public function __construct(array $attributes = [], public ?array $permissions = null)
    {
        parent::__construct($attributes);
    }

    public function can($abilities, $arguments = []): bool
    {
        if (is_string($abilities) && str_contains($abilities, ' ')) {
            return is_null($this->permissions) || in_array($abilities, $this->permissions, true);
        }

        return parent::can($abilities, $arguments);
    }
}

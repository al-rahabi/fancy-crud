<?php

namespace FancyCrud\Services;

use Illuminate\Contracts\Auth\Authenticatable;

abstract class BaseService
{
    protected string $guard = 'web';

    protected string $platform = '';

    protected ?Authenticatable $user = null;

    public function __construct(string $platform = '')
    {
        $this->user = auth($this->guard)->user();
        $this->platform = $platform;
        $this->initialize();
    }

    /**
     * Additional service initialization.
     */
    protected function initialize(): void {}

    /**
     * The authenticated user of `$guard`. Resolved lazily, so a service built
     * before login (or a long-lived one) still sees the current user.
     */
    public function getUser(): ?Authenticatable
    {
        return $this->user ??= auth($this->guard)->user();
    }

    public static function instance(string $platform = ''): static
    {
        return new static($platform);
    }
}

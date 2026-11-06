<?php

declare(strict_types=1);

namespace FancyCrud\Services;

/**
 * Base of every service. A service holds business logic only: it takes typed
 * arguments and never reads the request, the authenticated user or policies —
 * authorization and the acting user belong to the controller.
 */
abstract class BaseService
{
    public function __construct(protected string $platform = '')
    {
        $this->initialize();
    }

    /**
     * Additional service initialization.
     */
    protected function initialize(): void {}

    public static function instance(string $platform = ''): static
    {
        return new static($platform);
    }
}

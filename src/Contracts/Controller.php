<?php

declare(strict_types=1);

namespace FancyCrud\Contracts;

use FancyCrud\Services\BaseCrudService;
use FancyCrud\Services\BaseService;
use FancyCrud\Traits\ResponseTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    use ResponseTrait;

    /** Guard the authenticated user is resolved from. */
    protected string $guard = 'web';

    protected BaseService $service;

    public function __construct()
    {
        $this->service = $this->service();
    }

    /**
     * Resolve the service that powers this controller.
     * Each concrete controller must supply its own service instance.
     */
    abstract protected function service(): BaseService|BaseCrudService;

    /**
     * The authenticated user. Controllers (and only controllers) read it, to
     * authorize and to pass the actor into services as plain data.
     */
    protected function user(): ?Authenticatable
    {
        return auth($this->guard)->user();
    }
}

<?php

namespace FancyCrud\Contracts;

use FancyCrud\Services\BaseCrudService;
use FancyCrud\Services\BaseService;
use FancyCrud\Traits\ResponseTrait;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    use ResponseTrait;

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
}

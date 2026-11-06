<?php

declare(strict_types=1);

namespace FancyCrud\Actions\Crud;

use Illuminate\Database\Eloquent\Model;

class CreateAction extends AbstractAction
{
    public function execute(): Model
    {
        return $this->service->createMethod(
            array_merge($this->payload, $this->service->storeAdditionalData($this->payload))
        );
    }
}

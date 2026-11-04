<?php

namespace FancyCrud\Actions\Crud;

use Illuminate\Database\Eloquent\Model;

class CreateAction extends AbstractAction
{
    public function execute(): Model
    {
        $this->authorize('create');

        $data = array_merge($this->payload, $this->service->storeAdditionalData($this->payload));
        $this->service->beforeStore($data);

        $created = $this->service->createMethod($data);

        $this->service->afterStore($created, $data);

        return $created;
    }
}

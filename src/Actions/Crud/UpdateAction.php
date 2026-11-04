<?php

namespace FancyCrud\Actions\Crud;

use Illuminate\Database\Eloquent\Model;

class UpdateAction extends AbstractAction
{
    public function execute(): ?Model
    {
        $item = $this->target instanceof Model
            ? $this->target
            : $this->service->findItem($this->target);

        if (is_null($item)) {
            return null;
        }

        $this->authorize('update', $item);

        if (method_exists($item, 'canUpdate') && ! $item->canUpdate()) {
            return null;
        }

        $data = array_merge($this->payload, $this->service->updateAdditionalData($this->payload));
        $this->service->beforeUpdate($data);
        $this->service->updateMethod($item, $data);
        $this->service->afterUpdate($item, $data);

        return $item;
    }
}

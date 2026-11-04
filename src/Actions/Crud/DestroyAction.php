<?php

namespace FancyCrud\Actions\Crud;

use Illuminate\Database\Eloquent\Model;

class DestroyAction extends AbstractAction
{
    public function execute(): bool
    {
        $item = $this->target instanceof Model
            ? $this->target
            : $this->service->findItem($this->target);

        if (is_null($item)) {
            return false;
        }

        $this->authorize('delete', $item);

        if (method_exists($item, 'canDelete') && ! $item->canDelete()) {
            return false;
        }

        $this->service->beforeDelete($item);

        $result = $item->delete();

        if ($result) {
            $this->service->afterDelete();
        }

        return $result;
    }
}

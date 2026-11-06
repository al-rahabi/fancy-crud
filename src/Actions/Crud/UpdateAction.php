<?php

declare(strict_types=1);

namespace FancyCrud\Actions\Crud;

use Illuminate\Database\Eloquent\Model;

class UpdateAction extends AbstractAction
{
    /**
     * @return Model|null null when the target does not exist
     */
    public function execute(): ?Model
    {
        $item = $this->item();
        if ($item === null) {
            return null;
        }

        return $this->service->updateMethod(
            $item,
            array_merge($this->payload, $this->service->updateAdditionalData($this->payload))
        );
    }
}

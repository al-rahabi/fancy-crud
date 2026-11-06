<?php

declare(strict_types=1);

namespace FancyCrud\Actions\Crud;

class DestroyAction extends AbstractAction
{
    /**
     * @return bool false when the target does not exist or was not deleted
     */
    public function execute(): bool
    {
        return (bool) $this->item()?->delete();
    }
}

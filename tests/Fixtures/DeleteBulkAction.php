<?php

namespace FancyCrud\Tests\Fixtures;

use FancyCrud\Bulk\BulkAction;
use FancyCrud\Bulk\HandlesBySync;
use Illuminate\Database\Eloquent\Model;

/** Per-record, immediate; guarded by the `delete` ability. */
class DeleteBulkAction extends BulkAction implements HandlesBySync
{
    public function ability(): string
    {
        return 'delete';
    }

    public function bySync(Model $model, array $extra): void
    {
        $model->delete();
    }
}

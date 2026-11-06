<?php

namespace FancyCrud\Tests\Fixtures;

use FancyCrud\Bulk\BulkAction;
use FancyCrud\Bulk\HandlesByJob;
use Illuminate\Database\Eloquent\Model;

/** Queued, per record; chunks of 2 so chunking is exercised. */
class ArchiveBulkAction extends BulkAction implements HandlesByJob
{
    public function chunkSize(): int
    {
        return 2;
    }

    public function byJob(Model $model, array $extra): void
    {
        $model->update(['status' => 'archived']);
    }
}

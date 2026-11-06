<?php

namespace FancyCrud\Bulk;

use Illuminate\Database\Eloquent\Model;

/**
 * Queued, record by record: the request returns immediately and a job walks the
 * selection in chunks, calling this for every model. Model events fire.
 */
interface HandlesByJob
{
    public function byJob(Model $model, array $extra): void;
}

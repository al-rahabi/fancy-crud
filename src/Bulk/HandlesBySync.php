<?php

namespace FancyCrud\Bulk;

use Illuminate\Database\Eloquent\Model;

/**
 * Immediate, record by record: the selection is walked in chunks during the
 * request, calling this for every model. Model events fire.
 */
interface HandlesBySync
{
    public function bySync(Model $model, array $extra): void;
}

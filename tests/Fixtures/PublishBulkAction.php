<?php

namespace FancyCrud\Tests\Fixtures;

use FancyCrud\Bulk\BulkAction;
use FancyCrud\Bulk\HandlesByQuery;
use Illuminate\Database\Eloquent\Builder;

/** Set-based; `status` is required extra data. */
class PublishBulkAction extends BulkAction implements HandlesByQuery
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:published,archived'],
            'note' => 'nullable|string',
        ];
    }

    public function byQuery(Builder $query, array $extra): ?int
    {
        return $query->update(['status' => $extra['status']]);
    }
}

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

    /** @var array<string, mixed> What the last run received (lets tests see which keys got through). */
    public static array $lastExtra = [];

    public function byQuery(Builder $query, array $extra): ?int
    {
        self::$lastExtra = $extra;

        return $query->update(['status' => $extra['status']]);
    }
}

<?php

namespace FancyCrud\Tests\Fixtures;

use FancyCrud\Repositories\BaseRepository;

/** No `$model`: it comes from the table. */
class PostTableRepository extends BaseRepository
{
    protected ?string $table = PostTable::class;

    protected array $bulkActions = [
        PublishBulkAction::class,
        DeleteBulkAction::class,
        ArchiveBulkAction::class,
    ];
}

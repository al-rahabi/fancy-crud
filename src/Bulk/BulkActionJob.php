<?php

namespace FancyCrud\Bulk;

use FancyCrud\Repositories\BaseRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Runs a {@see HandlesByJob} action over a selection. It carries class names and
 * plain arrays only, so it serializes cleanly; there is no request or user inside.
 */
class BulkActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @param  class-string<BaseRepository>  $repository
     * @param  class-string<BulkAction>  $action
     */
    public function __construct(
        public string $repository,
        public string $action,
        public array $selection,
        public array $extra = [],
    ) {}

    public function handle(): void
    {
        (new BulkRunner(new $this->repository))->process(new $this->action, $this->selection, $this->extra);
    }
}

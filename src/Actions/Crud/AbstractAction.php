<?php

declare(strict_types=1);

namespace FancyCrud\Actions\Crud;

use FancyCrud\Services\BaseCrudService;
use Illuminate\Database\Eloquent\Model;

/**
 * One write verb. Actions are request-free and user-free: no authorization and
 * no hooks, only the write itself.
 */
abstract class AbstractAction
{
    protected int|string|Model|null $target = null;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        protected BaseCrudService $service,
        protected array $payload = []
    ) {}

    public function for(int|string|Model $idOrModel): static
    {
        $this->target = $idOrModel;

        return $this;
    }

    protected function item(): ?Model
    {
        return $this->target === null ? null : $this->service->findItem($this->target);
    }

    abstract public function execute(): mixed;
}

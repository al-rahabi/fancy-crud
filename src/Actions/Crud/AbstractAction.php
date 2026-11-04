<?php

namespace FancyCrud\Actions\Crud;

use FancyCrud\Services\BaseCrudService;
use Illuminate\Database\Eloquent\Model;

abstract class AbstractAction
{
    protected int|string|Model|null|array $target = null;

    public function __construct(
        protected BaseCrudService $service,
        protected array $payload = []
    ) {}

    /**
     * Check if the user is authorized to perform the action.
     */
    public function authorize(string $permission, mixed $model = null): bool
    {
        $user = $this->service->getUser();
        if ($user && ! $user->can($this->service->getPermissionName($permission), $model)) {
            abort(403);
        }

        return true;
    }

    public function for(int|string|Model $idOrModel): static
    {
        $this->target = $idOrModel;

        return $this;
    }

    /**
     * Execute the action.
     */
    abstract public function execute(): mixed;
}

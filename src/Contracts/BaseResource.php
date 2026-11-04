<?php

namespace FancyCrud\Contracts;

use Countable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;

class BaseResource extends JsonResource
{
    protected bool $search = false;

    protected bool $detailed = false;

    protected bool $hasAbilities = false;

    protected string $platform = '';

    protected ?Authenticatable $user = null;

    public function toArray(Request $request): array
    {
        $result = $this->toDefaultArray($request);
        if ($this->detailed) {
            $result = array_merge(
                $result,
                $this->toDetailedArray($request)
            );
        }
        $abilities = $this->abilities($request);
        if (count($abilities) > 0) {
            return array_merge(
                $result,
                ['abilities' => $abilities]
            );
        }

        return $result;
    }

    protected function toDefaultArray(Request $request): array
    {
        if (! empty($this->platform)) {
            $methodName = 'to'.ucfirst($this->platform).'Array';
            if (method_exists($this, $methodName)) {
                return $this->{$methodName}($request, $this);
            }
        }

        return $this->resource->toArray();
    }

    protected function toDetailedArray(Request $request): array
    {
        return [];
    }

    protected function abilities(Request $request): array
    {
        return [];
    }

    public function withDetail(bool $detailed = true): static
    {
        $this->detailed = $detailed;

        return $this;
    }

    public function withPlatform(string $platform = ''): static
    {
        $this->platform = $platform;

        return $this;
    }

    public function setUser(?Authenticatable $user = null): static
    {
        $this->user = $user;

        return $this;
    }

    public function withAbility(?Authenticatable $user, bool $withAbilities = true): static
    {
        $this->hasAbilities = $withAbilities;
        $this->user = $user;

        return $this;
    }

    public static function makeWithDetail(mixed $resource, ?Authenticatable $user = null, bool $detailed = true, string $platform = ''): JsonResource
    {
        return self::make($resource)->withDetail($detailed)->setUser($user)->withPlatform($platform);
    }

    public static function makeCollection(Countable|MissingValue $resource, ?Authenticatable $user = null, bool $detailed = false, string $platform = ''): Collection|MissingValue
    {
        if ($resource instanceof MissingValue) {
            return $resource;
        }
        $collection = $resource instanceof AbstractPaginator
            ? $resource->getCollection()
            : collect($resource);
        $mapped = $collection->map(fn ($item) => self::make($item)->setUser($user)->withDetail($detailed)->withPlatform($platform));

        if ($resource instanceof AbstractPaginator) {
            return $resource->setCollection($mapped);
        }

        return $mapped->values();
    }
}

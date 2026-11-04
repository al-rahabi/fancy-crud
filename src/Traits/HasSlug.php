<?php

namespace FancyCrud\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @mixin Model
 */
trait HasSlug
{
    private static function hasArabicCharacters($text): bool
    {
        return preg_match('/\p{Arabic}/u', $text) === 1;
    }

    protected static function generateSlug(Model $item): void
    {
        foreach ($item->sluggable() as $options) {
            if (! empty($item->{$options['slug_col']})) {
                continue;
            }

            $separator = $options['separator'] ?? '_';

            if (! is_callable($options['col'])) {
                $column = $item->{"{$options['col']}"};
                $column = self::hasArabicCharacters($column) ? Str::ascii($column) : $column;
                $slug = Str::lower(Str::singular(Str::snake($column)));
            } else {
                $slug = $options['col']();
            }
            $slug = Str::replace('_', $separator, $slug);

            if ($item->shouldBeUnique ?? false) {
                $notUnique = self::checkForUniqueness($options, $slug);
                $loopIndex = 0;
                while ($notUnique) {
                    $num = $item->id + $loopIndex;
                    $newSlug = "{$slug}{$separator}{$num}";
                    $notUnique = self::checkForUniqueness($options, $newSlug);
                    $loopIndex++;
                }
            }

            $forceSlug = is_callable($options['force_slug'] ?? null) ? $options['force_slug']($item) : null;

            $item->{"{$options['slug_col']}"} = $forceSlug ?? $newSlug ?? $slug;
        }
    }

    protected static function booted(): void
    {
        parent::booted();
        self::creating(function (Model $item) {
            self::generateSlug($item);
        });

        self::updating(function (Model $item) {
            self::generateSlug($item);
        });
    }

    public function sluggable(): array
    {
        return [];
    }

    private static function checkForUniqueness(array $options, string $slug): bool
    {
        if (! isset($options['unique_cond'])) {
            $uniqueQuery = self::query();
            $uniqueQuery->where("{$options['slug_col']}", $slug);

            return $uniqueQuery->exists();
        }

        return (bool) $options['unique_cond']($slug);
    }

    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where(fn ($q) => array_map(fn ($sluggable) => $query->where("{$sluggable['slug_col']}", $slug), $this->sluggable()));
    }
}

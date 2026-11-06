<?php

declare(strict_types=1);

namespace FancyCrud\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The single place that derives permission names, shared by the write side
 * ({@see \FancyCrud\Services\BaseCrudService}) and the read side
 * ({@see \FancyCrud\Contracts\BasePolicy}) so they cannot drift apart.
 *
 * `BlogPost` → resource `blog posts` → permission `update blog posts`.
 */
final class PermissionName
{
    /** Plural, snake-cased, space separated resource for a class name / model / model class. */
    public static function resource(string|Model $subject): string
    {
        $name = class_basename($subject instanceof Model ? $subject::class : $subject);

        return Str::plural(Str::snake($name, ' '));
    }

    /** `"{action} {resource}"` */
    public static function for(string|Model $subject, string $action): string
    {
        return "{$action} ".self::resource($subject);
    }
}

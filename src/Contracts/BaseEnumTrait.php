<?php

namespace FancyCrud\Contracts;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

trait BaseEnumTrait
{
    public static function toArray(): array
    {
        $array = [];
        foreach (static::cases() as $definition) {
            $array[$definition->value] = $definition->name;
        }

        return $array;
    }

    public static function toValues(): array
    {
        return array_keys(static::toArray());
    }

    public static function toRule(): In
    {
        return Rule::in(static::toValues());
    }
}

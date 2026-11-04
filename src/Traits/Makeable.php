<?php

namespace FancyCrud\Traits;

trait Makeable
{
    public static function make(...$arguments): self
    {
        return new static(...$arguments);
    }
}

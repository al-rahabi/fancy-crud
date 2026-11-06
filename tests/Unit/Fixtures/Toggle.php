<?php

namespace FancyCrud\Tests\Unit\Fixtures;

use FancyCrud\Traits\Activable;
use Illuminate\Database\Eloquent\Model;

class Toggle extends Model
{
    use Activable;

    protected $table = 'toggles';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];
}

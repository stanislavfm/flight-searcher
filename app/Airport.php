<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string|null $name
 * @property string $location
 * @property int $timezoneOffset
 * @mixin \Eloquent
 */
class Airport extends Model
{
    public $timestamps = false;
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Eccube extends Model
{
    protected $table = 'ec_cube';
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'webhook',
        'type',
    ];
}

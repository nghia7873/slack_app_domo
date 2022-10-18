<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Linkedin extends Model
{
    protected $table = 'linkedin';
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'link',
        'status'
    ];
}

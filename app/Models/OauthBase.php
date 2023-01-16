<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OauthBase extends Model
{
    protected $table = 'oauth2_base';
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'access_token',
        'refresh_token',
    ];
}

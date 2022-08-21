<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Slack extends Model
{
    protected $table = 'slacks';
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'token',
        'webhook_domo',
        'channel_bot_id'
    ];
}

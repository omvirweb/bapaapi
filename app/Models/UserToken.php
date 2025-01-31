<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserToken extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'api_token', 'device_name'];

    // Define relationship to user
    public function user()
    {
        return $this->belongsTo(userslogin::class, 'user_id');
    }
}

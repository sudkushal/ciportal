<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    
    protected $allowedFields = [
        'strava_id',
        'firstname',
        'lastname',
        'profile_pic',
        'access_token',
        'refresh_token',
        'expires_at',
    ];
}

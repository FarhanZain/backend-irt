<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{

    protected $table = 'users';
    protected $primaryKey = 'id_user';
    public $timestamps = false;

    protected $fillable = [
        'nama',
        'role',
        'password'
    ];

    protected $hidden = [
        'password',
    ];

    public function ujianPeserta()
    {
        return $this->hasMany(UjianPeserta::class, 'user_id');
    }

    public function irtPeserta()
    {
        return $this->hasMany(IrtPeserta::class, 'user_id');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    protected $table = 'notifikasi';
    public $timestamps = false; // Tanpa created_at dan updated_at
    protected $fillable = ['paket_id', 'nama_paket_snapshot', 'tipe'];
}

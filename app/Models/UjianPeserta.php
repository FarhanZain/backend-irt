<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UjianPeserta extends Model
{
    protected $table = 'ujian_peserta';
    protected $primaryKey = 'id_ujian';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'paket_id',
        'waktu_mulai',
        'waktu_selesai'
    ];

    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id',
            'id_user'
        );
    }

    public function paket()
    {
        return $this->belongsTo(
            PaketSoal::class,
            'paket_id',
            'id_paket'
        );
    }

    public function jawabanPeserta()
    {
        return $this->hasMany(
            JawabanPeserta::class,
            'ujian_id'
        );
    }
}
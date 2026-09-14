<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaketSoal extends Model
{
    protected $table = 'paket_soal';
    protected $primaryKey = 'id_paket';

    public $timestamps = false;

    protected $fillable = [
        'nama_paket',
        'jadwal_mulai',
        'jadwal_selesai',
        'tipe_soal'
    ];

    public function topik()
    {
        return $this->hasMany(Topik::class, 'paket_id');
    }

    public function ujianPeserta()
    {
        return $this->hasMany(UjianPeserta::class, 'paket_id');
    }

    public function irtSoal()
    {
        return $this->hasMany(IrtSoal::class, 'paket_id');
    }

    public function irtPeserta()
    {
        return $this->hasMany(IrtPeserta::class, 'paket_id');
    }

    public function soal()
    {
        return $this->hasManyThrough(
            Soal::class,       // Model tujuan akhir
            Topik::class,      // Model perantara
            'paket_id',        // Foreign key di tabel topik
            'topik_id',        // Foreign key di tabel soal
            'id_paket',        // Local key di tabel paket_soal
            'id_topik'         // Local key di tabel topik
        );
    }
}
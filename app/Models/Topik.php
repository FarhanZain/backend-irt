<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Topik extends Model
{
    protected $table = 'topik';
    protected $primaryKey = 'id_topik';
    public $timestamps = false;

    protected $fillable = [
        'paket_id',
        'nama_topik'
    ];

    public function paket()
    {
        return $this->belongsTo(
            PaketSoal::class,
            'paket_id',
            'id_paket'
        );
    }

    public function soal()
    {
        return $this->hasMany(
            Soal::class,
            'topik_id'
        );
    }

    public function irtPeserta()
    {
        return $this->hasMany(
            IrtPeserta::class,
            'topik_id'
        );
    }
}
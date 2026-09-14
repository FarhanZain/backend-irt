<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JawabanSoal extends Model
{
    protected $table = 'jawaban_soal';
    protected $primaryKey = 'id_jawaban_soal';
    public $timestamps = false;

    protected $fillable = [
        'soal_id',
        'teks_jawaban',
        'is_kunci_jawaban'
    ];

    public function soal()
    {
        return $this->belongsTo(
            Soal::class,
            'soal_id',
            'id_soal'
        );
    }

    public function jawabanPeserta()
    {
        return $this->hasMany(
            JawabanPeserta::class,
            'jawaban_soal_id'
        );
    }
}
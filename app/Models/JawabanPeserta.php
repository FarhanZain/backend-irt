<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JawabanPeserta extends Model
{
    protected $table = 'jawaban_peserta';
    protected $primaryKey = 'id_jawaban_peserta';
    public $timestamps = false;

    protected $fillable = [
        'ujian_id',
        'soal_id',
        'jawaban_soal_id',
        'is_benar'
    ];

    public function ujian()
    {
        return $this->belongsTo(
            UjianPeserta::class,
            'ujian_id',
            'id_ujian'
        );
    }

    public function soal()
    {
        return $this->belongsTo(
            Soal::class,
            'soal_id',
            'id_soal'
        );
    }

    public function jawaban()
    {
        return $this->belongsTo(
            JawabanSoal::class,
            'jawaban_soal_id',
            'id_jawaban_soal'
        );
    }
}
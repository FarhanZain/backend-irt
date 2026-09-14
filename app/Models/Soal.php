<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Soal extends Model
{
    protected $table = 'soal';
    protected $primaryKey = 'id_soal';
    public $timestamps = false;

    protected $fillable = [
        'topik_id',
        'pertanyaan'
    ];

    public function topik()
    {
        return $this->belongsTo(
            Topik::class,
            'topik_id',
            'id_topik'
        );
    }

    public function jawaban()
    {
        return $this->hasMany(
            JawabanSoal::class,
            'soal_id'
        );
    }

    public function jawabanPeserta()
    {
        return $this->hasMany(
            JawabanPeserta::class,
            'soal_id'
        );
    }

    public function irtSoal()
    {
        return $this->hasOne(
            IrtSoal::class,
            'soal_id'
        );
    }
}
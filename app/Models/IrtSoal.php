<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IrtSoal extends Model
{
    protected $table = 'irt_soal';
    protected $primaryKey = 'id_irt_soal';
    public $timestamps = false;

    protected $fillable = [
        'paket_id',
        'soal_id',
        'tingkat_kesulitan',
        'kategori_kesulitan',
        'daya_pembeda',
        'kategori_daya_pembeda',
        'tebakan',
        'model_irt'
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
        return $this->belongsTo(
            Soal::class,
            'soal_id',
            'id_soal'
        );
    }
}
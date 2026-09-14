<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IrtPeserta extends Model
{
    protected $table = 'irt_peserta';
    protected $primaryKey = 'id_irt_peserta';
    public $timestamps = false;

    protected $fillable = [
        'paket_id',
        'user_id',
        'topik_id',
        'theta',
        'skor',
        'akurasi',
        'status_kemampuan'
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

    public function topik()
    {
        return $this->belongsTo(
            Topik::class,
            'topik_id',
            'id_topik'
        );
    }
}
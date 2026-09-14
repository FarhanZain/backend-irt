<?php

namespace App\Http\Controllers;

use App\Models\PaketSoal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class PengembangPaketSoalController extends Controller
{
    // Halaman List: Mengambil semua paket dengan tambahan jumlah peserta yang mengerjakan
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 5);
        
        // Tambahkan 'ujianPeserta' ke dalam withCount
        $paket = PaketSoal::withCount(['topik', 'soal', 'ujianPeserta'])
            ->orderBy('id_paket', 'desc')
            ->paginate($perPage);

        return response()->json($paket);
    }

    // Halaman Detail: Ditambahkan jumlah peserta dan status pengolahan IRT
    public function show($id)
    {
        // 1. Ambil data paket beserta hitungan relasinya
        $paket = PaketSoal::withCount(['topik', 'soal', 'ujianPeserta'])
            ->with(['topik' => function($query) {
                $query->withCount('soal'); 
            }])
            ->findOrFail($id);

        // 2. Cek apakah sudah ada data hasil olahan di tabel irt_peserta ATAU irt_soal
        // Anda bisa menyesuaikan, misalnya hanya cek di irtPeserta saja juga boleh
        $sudahAdaIrtPeserta = $paket->irtPeserta()->exists();
        $sudahAdaIrtSoal = $paket->irtSoal()->exists();

        // Tentukan status berdasarkan keberadaan data tersebut
        $statusIrt = ($sudahAdaIrtPeserta || $sudahAdaIrtSoal) ? 'sudah diolah' : 'belum diolah';

        // 3. Masukkan informasi status_irt ke dalam response data paket
        $paket->status_irt = $statusIrt;

        return response()->json([
            'status' => 'success',
            'data' => $paket
        ]);
    }
}
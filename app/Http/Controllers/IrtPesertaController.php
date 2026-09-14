<?php

namespace App\Http\Controllers;

use App\Models\IrtPeserta;
use App\Models\JawabanPeserta;
use App\Models\JawabanSoal;
use App\Models\PaketSoal;
use App\Models\UjianPeserta;
use Illuminate\Http\Request;

class IrtPesertaController extends Controller
{
    /**
     * Mengambil data olahan IRT untuk Dashboard Hasil Ujian Peserta
     */
    public function getDashboardIrt(Request $request, $paket_id)
    {
        // Ambil user_id dari query parameter (?user_id=xx)
        $user_id = $request->query('user_id') ?? $request->input('id_user') ?? auth()->id();

        if (!$user_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter user_id tidak ditemukan atau tidak valid.'
            ], 400);
        }

        // 1. Ambil info paket & ujian peserta terkait
        $paket = PaketSoal::find($paket_id);
        $ujian = UjianPeserta::where('paket_id', $paket_id)
                            ->where('user_id', $user_id)
                            ->first();

        if (!$paket || !$ujian) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data ujian atau paket untuk user ini tidak ditemukan.'
            ], 404);
        }

        // ==========================================
        // SECTION 1: RINGKASAN & STATISTIK JAWABAN
        // ==========================================
        // Rata-rata theta dihapus/tidak digunakan untuk skor akhir sesuai instruksi Anda

        $statistikJawaban = JawabanPeserta::where('ujian_id', $ujian->id_ujian)
            ->selectRaw("
                COUNT(CASE WHEN is_benar = 1 THEN 1 END) as jumlah_benar,
                COUNT(CASE WHEN is_benar = 0 AND jawaban_soal_id IS NOT NULL THEN 1 END) as jumlah_salah,
                COUNT(CASE WHEN jawaban_soal_id IS NULL THEN 1 END) as jumlah_kosong,
                COUNT(*) as total_soal
            ")->first();

        // ==========================================
        // SECTION 2: DISTRIBUSI KEMAMPUAN (POWER)
        // ==========================================
        $irtPesertaTopik = IrtPeserta::with('topik')
            ->where('paket_id', $paket_id)
            ->where('user_id', $user_id)
            ->get();

        $kekuatan = [];
        $normal = [];
        $kelemahan = [];
        
        // Variabel bantuan untuk menghitung rata-rata skor hasil scaling
        $totalSkorTopik = 0;
        $jumlahTopik = $irtPesertaTopik->count();

        foreach ($irtPesertaTopik as $item) {
            // Akumulasikan skor hasil scaling dari setiap topik
            $totalSkorTopik += $item->skor;

            $statTopik = JawabanPeserta::where('ujian_id', $ujian->id_ujian)
                ->whereHas('soal', function($query) use ($item) {
                    $query->where('topik_id', $item->topik_id);
                })
                ->selectRaw("
                    COUNT(CASE WHEN is_benar = 1 THEN 1 END) as benar,
                    COUNT(CASE WHEN is_benar = 0 AND jawaban_soal_id IS NOT NULL THEN 1 END) as salah,
                    COUNT(CASE WHEN jawaban_soal_id IS NULL THEN 1 END) as kosong,
                    COUNT(*) as total
                ")->first();

            $formattedTopik = [
                'nama_topik'     => $item->topik->nama_topik ?? 'Tanpa Nama Topik',
                'id_topik'       => $item->topik_id,
                'jumlah_soal'    => $statTopik->total ?? 0,
                'jumlah_benar'   => $statTopik->benar ?? 0,
                'jumlah_salah'   => $statTopik->salah ?? 0,
                'jumlah_kosong'  => $statTopik->kosong ?? 0,
                'skor_kemampuan' => round($item->skor, 2), 
                'akurasi'        => $item->akurasi . '%'
            ];

            $status = strtolower($item->status_kemampuan);

            if (str_contains($status, 'kuat') || $status === 'kekuatan') {
                $kekuatan[] = $formattedTopik;
            } elseif (str_contains($status, 'lemah') || $status === 'kelemahan') {
                $kelemahan[] = $formattedTopik;
            } else {
                $normal[] = $formattedTopik;
            }
        }

        // Hitung rata-rata hasil scaling skor dari semua topik
        $rataRataSkorScaling = $jumlahTopik > 0 ? ($totalSkorTopik / $jumlahTopik) : 0;

        // ==========================================
        // SECTION 3: TINGKAT KESULITAN SOAL (LEVEL)
        // ==========================================
        $jawabanList = JawabanPeserta::with(['soal.topik', 'soal.irtSoal', 'jawaban']) 
            ->where('ujian_id', $ujian->id_ujian)
            ->get();

        $tingkatKesulitanSoal = [];

        foreach ($jawabanList as $jawaban) {
            $soalNode = $jawaban->soal;
            $irtSoalNode = $soalNode->irtSoal ?? null;

            $statusJawaban = 'kosong';
            if (!is_null($jawaban->jawaban_soal_id)) {
                $statusJawaban = $jawaban->is_benar == 1 ? 'benar' : 'salah';
            }

            $kunciJawabanTeks = JawabanSoal::where('soal_id', $soalNode->id_soal)
                                ->where('is_kunci_jawaban', 1)
                                ->value('teks_jawaban');

            $tingkatKesulitanSoal[] = [
                'id_soal'         => $soalNode->id_soal, 
                'level_kesulitan' => $irtSoalNode->kategori_kesulitan ?? '-', 
                'nama_topik'      => $soalNode->topik->nama_topik,
                'pertanyaan'      => $soalNode->pertanyaan,
                'status_jawaban'  => $statusJawaban,
                'jawaban_peserta' => $jawaban->jawaban->teks_jawaban ?? null, 
                'kunci_jawaban'   => $kunciJawabanTeks
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'nama_paket' => $paket->nama_paket,
                'total_skor_akhir' => round($rataRataSkorScaling, 2),
                'statistik_jawaban' => [
                    'jumlah_benar'  => $statistikJawaban->jumlah_benar ?? 0,
                    'jumlah_salah'  => $statistikJawaban->jumlah_salah ?? 0,
                    'jumlah_kosong' => $statistikJawaban->jumlah_kosong ?? 0,
                    'total_soal'    => $statistikJawaban->total_soal ?? 0,
                ],
                'kemampuan_per_sub_materi' => [
                    'kekuatan'  => $kekuatan,
                    'normal'    => $normal,
                    'kelemahan' => $kelemahan,
                ],
                'tingkat_kesulitan_soal' => $tingkatKesulitanSoal
            ]
        ]);
    }
}
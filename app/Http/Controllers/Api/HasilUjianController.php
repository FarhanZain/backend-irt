<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class HasilUjianController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════════════
    // HALAMAN 3 – Dashboard Hasil IRT Peserta
    // GET /api/v1/dashboard/{id_paket}/{id_peserta}
    //
    // Response mencakup:
    //   - info_peserta      : nama, paket
    //   - ringkasan_ujian   : total skor, rata-rata probabilitas
    //   - statistik_jawaban : jumlah benar, salah, kosong
    //   - kemampuan_per_sub_materi : theta, skor_skala, status (Lemah/Normal/Kuat)
    //     → dikelompokkan menjadi kelemahan / normal / kekuatan
    //   - tingkat_kesulitan_soal   : difficulty, discrimination, kategori per soal
    // ═══════════════════════════════════════════════════════════════════════════

    public function dashboard(int $id_paket, int $id_peserta): JsonResponse
    {
        // ── 1. Validasi paket & peserta ────────────────────────────────────────
        $paket = DB::table('paket_soal')->where('id_paket', $id_paket)->first();
        if (! $paket) {
            return response()->json([
                'success' => false,
                'message' => "Paket soal dengan id {$id_paket} tidak ditemukan",
            ], 404);
        }

        $peserta = DB::table('peserta')->where('id_peserta', $id_peserta)->first();
        if (! $peserta) {
            return response()->json([
                'success' => false,
                'message' => "Peserta dengan id {$id_peserta} tidak ditemukan",
            ], 404);
        }

        // ── 2. Ringkasan akhir ujian ───────────────────────────────────────────
        $ringkasan = DB::table('ringkasan_akhir_ujian')
            ->where('id_paket', $id_paket)
            ->where('id_peserta', $id_peserta)
            ->first();

        if (! $ringkasan) {
            return response()->json([
                'success' => false,
                'message' => 'Data hasil IRT peserta ini belum tersedia',
            ], 404);
        }

        // ── 3. Statistik jawaban (benar / salah / kosong) ─────────────────────
        // jawaban_peserta: id_peserta, id_soal, jawaban (1=benar, 0=salah),
        //                  id_paket_soal
        $jawaban = DB::table('hasil_ujian as hu')
            ->join('soal as s', 's.id_soal', '=', 'hu.id_soal')
            ->where('hu.id_peserta', $id_peserta)
            ->where('s.id_paket_soal', $id_paket)
            ->select('hu.jawaban')
            ->get();

        $totalSoal   = $jawaban->count();
        $jumlahBenar = $jawaban->where('jawaban', 1)->count();
        $jumlahSalah = $jawaban->where('jawaban', 0)->count();
        // Soal yang tidak dijawab sama sekali (kosong) dihitung dari selisih
        // total soal dalam paket dengan total baris jawaban peserta
        $totalSoalPaket  = DB::table('soal')->where('id_paket_soal', $id_paket)->count();
        $jumlahKosong    = $totalSoalPaket - $totalSoal;

        // Rata-rata skor (mean skor_skala seluruh sub materi peserta ini)
        $rataRataSkor = DB::table('hasil_kemampuan_peserta')
            ->where('id_paket', $id_paket)
            ->where('id_peserta', $id_peserta)
            ->avg('skor_skala');

        // ── 4. Kemampuan per sub materi (DENGAN STATISTIK JAWABAN) ──────────────
        $kemampuanRaw = DB::table('hasil_kemampuan_peserta as hkp')
            ->join('sub_materi as sm', 'sm.id_sub_materi', '=', 'hkp.id_sub_materi')
            ->leftJoin('soal as s', 's.id_sub_materi', '=', 'sm.id_sub_materi')
            ->leftJoin('hasil_ujian as hu', function($join) use ($id_peserta) {
                $join->on('hu.id_soal', '=', 's.id_soal')
                    ->where('hu.id_peserta', '=', $id_peserta);
            })
            ->where('hkp.id_paket', $id_paket)
            ->where('hkp.id_peserta', $id_peserta)
            ->where('s.id_paket_soal', $id_paket)
            ->select(
                'sm.id_sub_materi',
                'sm.nama_sub_materi',
                // 'hkp.theta',
                'hkp.skor_skala',
                'hkp.probabilitas',
                'hkp.status_kemampuan',
                DB::raw('COUNT(s.id_soal) as total_soal_sub'),
                DB::raw('SUM(CASE WHEN hu.jawaban = 1 THEN 1 ELSE 0 END) as jumlah_benar'),
                DB::raw('SUM(CASE WHEN hu.jawaban = 0 AND hu.id_hasil IS NOT NULL THEN 1 ELSE 0 END) as jumlah_salah'),
                DB::raw('SUM(CASE WHEN hu.id_hasil IS NULL THEN 1 ELSE 0 END) as jumlah_kosong')
            )
            ->groupBy(
                'sm.id_sub_materi', 
                'sm.nama_sub_materi', 
                // 'hkp.theta', 
                'hkp.skor_skala', 
                'hkp.probabilitas', 
                'hkp.status_kemampuan'
            )
            ->orderBy('sm.id_sub_materi')
            ->get();

        // Lakukan mapping untuk memastikan tipe data adalah Number (int/float)
        $kemampuan = $kemampuanRaw->map(function ($item) {
            return [
                'id_sub_materi'    => (int) $item->id_sub_materi,
                'nama_sub_materi'  => $item->nama_sub_materi,
                // 'theta'            => (float) $item->theta,
                'skor_skala'       => (float) $item->skor_skala,
                'probabilitas'     => (float) $item->probabilitas,
                'status_kemampuan' => $item->status_kemampuan,
                'total_soal_sub'   => (int) $item->total_soal_sub,
                'jumlah_benar'     => (int) $item->jumlah_benar,
                'jumlah_salah'     => (int) $item->jumlah_salah,
                'jumlah_kosong'    => (int) $item->jumlah_kosong,
            ];
        });

        // Kelompokkan berdasarkan status dari koleksi yang sudah di-mapping
        $kelemahan = $kemampuan->where('status_kemampuan', 'Lemah')->values();
        $normal    = $kemampuan->where('status_kemampuan', 'Normal')->values();
        $kekuatan  = $kemampuan->where('status_kemampuan', 'Kuat')->values();

        // ── 5. Tingkat kesulitan soal ──────────────────────────────────────────
        $jawabanPeserta = DB::table('hasil_ujian as hu')
            ->join('soal as s', 's.id_soal', '=', 'hu.id_soal')
            ->where('hu.id_peserta', $id_peserta)
            ->where('s.id_paket_soal', $id_paket)
            ->select('hu.id_soal', 'hu.jawaban')
            ->get()
            ->keyBy('id_soal');

        $analisisSoal = DB::table('analisis_soal_irt as asi')
            ->join('soal as s',        's.id_soal',        '=', 'asi.id_soal')
            ->join('sub_materi as sm', 'sm.id_sub_materi', '=', 's.id_sub_materi')
            ->where('asi.id_paket', $id_paket)
            ->select(
                'asi.id_soal',
                's.nomor_soal',
                'sm.nama_sub_materi',
                // 'asi.difficulty',
                // 'asi.discrimination',
                // 'asi.guessing',
                'asi.kategori',
                // 'asi.model_irt'
            )
            ->orderBy('s.nomor_soal')
            ->get()
            ->map(function ($soal) use ($jawabanPeserta) {
                $jawaban = $jawabanPeserta->get($soal->id_soal);
 
                // jawaban: 1=benar, 0=salah, null=tidak dijawab (kosong)
                $soal->jawaban_peserta    = isset($jawaban) ? (int) $jawaban->jawaban : null;
                $soal->keterangan_jawaban = match ($soal->jawaban_peserta) {
                    1       => 'Benar',
                    0       => 'Salah',
                    default => 'Kosong',
                };
 
                return $soal;
            });

        // ── 6. Build response ──────────────────────────────────────────────────
        return response()->json([
            'success' => true,
            'message' => 'Dashboard hasil IRT berhasil diambil',
            'data'    => [

                'info_peserta' => [
                    'id_peserta'   => $peserta->id_peserta,
                    'nama_peserta' => $peserta->nama_peserta,
                    'id_paket'     => $paket->id_paket,
                    'nama_paket'   => $paket->nama_paket,
                ],

                'ringkasan_ujian' => [
                    'total_skor_akhir' => $ringkasan->total_skor_akhir,
                    'rata_rata_prob'   => $ringkasan->rata_rata_prob,
                ],

                'statistik_jawaban' => [
                    'total_soal'    => $totalSoalPaket,
                    'jumlah_benar'  => $jumlahBenar,
                    'jumlah_salah'  => $jumlahSalah,
                    'jumlah_kosong' => $jumlahKosong,
                    'rata_rata_skor_irt' => round($rataRataSkor, 2),
                ],

                'kemampuan_per_sub_materi' => [
                    'kelemahan' => $kelemahan,   // status_kemampuan = Lemah
                    'normal'    => $normal,       // status_kemampuan = Normal
                    'kekuatan'  => $kekuatan,     // status_kemampuan = Kuat
                ],

                'tingkat_kesulitan_soal' => $analisisSoal,
            ],
        ]);
    }
}
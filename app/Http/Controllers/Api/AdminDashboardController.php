<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════════════
    // DASHBOARD ADMIN / PENGEMBANG SOAL
    // GET /api/admin/dashboard/{id_paket}
    //
    // Berisi:
    //   1. ringkasan_paket       → nama paket, jumlah peserta, rata-rata skor
    //   2. distribusi_kemampuan  → sebaran peserta per bucket theta & skor
    //   3. analisis_soal         → difficulty, discrimination, kategori tiap soal
    //   4. penguasaan_sub_materi → % peserta Lemah / Normal / Kuat per sub materi
    //   5. peringkat_peserta     → ranking berdasarkan rata-rata theta & skor
    // ═══════════════════════════════════════════════════════════════════════════

    public function dashboard(int $id_paket): JsonResponse
    {
        // ── Validasi paket ─────────────────────────────────────────────────────
        $paket = DB::table('paket_soal')->where('id_paket', $id_paket)->first();
        if (! $paket) {
            return response()->json([
                'success' => false,
                'message' => "Paket soal dengan id {$id_paket} tidak ditemukan",
            ], 404);
        }

        // ── 1. Ringkasan Paket ─────────────────────────────────────────────────
        // Jumlah peserta yang sudah ada hasil IRT-nya di paket ini
        $jumlahPeserta = DB::table('ringkasan_akhir_ujian')
            ->where('id_paket', $id_paket)
            ->count();

        // Rata-rata skor akhir & rata-rata probabilitas seluruh peserta
        $ringkasanPaket = DB::table('ringkasan_akhir_ujian')
            ->where('id_paket', $id_paket)
            ->selectRaw('
                AVG(total_skor_akhir) AS rata_rata_skor_akhir,
                AVG(rata_rata_prob)   AS rata_rata_prob,
                MIN(total_skor_akhir) AS skor_terendah,
                MAX(total_skor_akhir) AS skor_tertinggi
            ')
            ->first();

        // Rata-rata theta seluruh peserta (dari semua sub materi, di-avg per peserta dulu)
        $rataRataTheta = DB::table('hasil_kemampuan_peserta')
            ->where('id_paket', $id_paket)
            ->avg('theta');

        // ── 2. Distribusi Kemampuan ────────────────────────────────────────────
        // Ambil rata-rata theta & skor per peserta, lalu kelompokkan ke bucket
        $kemampuanPerPeserta = DB::table('hasil_kemampuan_peserta')
            ->where('id_paket', $id_paket)
            ->select(
                'id_peserta',
                DB::raw('AVG(theta)      AS avg_theta'),
                DB::raw('AVG(skor_skala) AS avg_skor')
            )
            ->groupBy('id_peserta')
            ->get();

        // Bucket theta: Sangat Lemah < -1.5 | Lemah -1.5~-0.5 | Sedang -0.5~0.5 | Kuat 0.5~1.5 | Sangat Kuat > 1.5
        $distribusiTheta = [
            'sangat_lemah' => 0,
            'lemah'        => 0,
            'sedang'       => 0,
            'kuat'         => 0,
            'sangat_kuat'  => 0,
        ];

        // Bucket skor: < 300 | 300-499 | 500-699 | 700-899 | >= 900
        $distribusiSkor = [
            'di_bawah_300'  => 0,
            '300_499'       => 0,
            '500_699'       => 0,
            '700_899'       => 0,
            '900_ke_atas'   => 0,
        ];

        foreach ($kemampuanPerPeserta as $p) {
            // Theta
            if ($p->avg_theta < -1.5)                              $distribusiTheta['sangat_lemah']++;
            elseif ($p->avg_theta >= -1.5 && $p->avg_theta < -0.5) $distribusiTheta['lemah']++;
            elseif ($p->avg_theta >= -0.5 && $p->avg_theta < 0.5)  $distribusiTheta['sedang']++;
            elseif ($p->avg_theta >= 0.5  && $p->avg_theta < 1.5)  $distribusiTheta['kuat']++;
            else                                                     $distribusiTheta['sangat_kuat']++;

            // Skor
            if ($p->avg_skor < 300)                                 $distribusiSkor['di_bawah_300']++;
            elseif ($p->avg_skor >= 300 && $p->avg_skor < 500)     $distribusiSkor['300_499']++;
            elseif ($p->avg_skor >= 500 && $p->avg_skor < 700)     $distribusiSkor['500_699']++;
            elseif ($p->avg_skor >= 700 && $p->avg_skor < 900)     $distribusiSkor['700_899']++;
            else                                                     $distribusiSkor['900_ke_atas']++;
        }

        $distribusiKemampuan = [
            'theta' => $distribusiTheta,
            'skor'  => $distribusiSkor,
        ];

        // ── 3. Analisis Soal ───────────────────────────────────────────────────
        $analisisSoal = DB::table('analisis_soal_irt as asi')
            ->join('soal as s',       's.id_soal',       '=', 'asi.id_soal')
            ->join('sub_materi as sm', 'sm.id_sub_materi', '=', 's.id_sub_materi')
            ->where('asi.id_paket', $id_paket)
            ->select(
                'asi.id_soal',
                's.nomor_soal',
                'sm.nama_sub_materi',
                'asi.difficulty',
                'asi.discrimination',
                'asi.guessing',
                'asi.kategori',
                'asi.model_irt'
            )
            ->orderBy('s.nomor_soal')
            ->get()
            ->map(function ($soal) {
                // Label tingkat discrimination
                $soal->label_discrimination = match (true) {
                    $soal->discrimination < 0  => 'Cacat',
                    $soal->discrimination < 0.35  => 'Sangat Rendah',
                    $soal->discrimination < 0.65  => 'Rendah',
                    $soal->discrimination < 1.35  => 'Sedang',
                    $soal->discrimination < 1.70  => 'Tinggi',
                    default                      => 'Sangat Tinggi',
                };
                return $soal;
            });

        // Rekap kategori soal
        $rekapKategori = [
            'mudah'  => $analisisSoal->where('kategori', 'Mudah')->count(),
            'sedang' => $analisisSoal->where('kategori', 'Sedang')->count(),
            'susah'  => $analisisSoal->where('kategori', 'Susah')->count(),
        ];

        // ── 4. Penguasaan Sub Materi ───────────────────────────────────────────
        // Hitung jumlah peserta per status per sub materi, lalu konversi ke %
        $statusPerSubMateri = DB::table('hasil_kemampuan_peserta as hkp')
            ->join('sub_materi as sm', 'sm.id_sub_materi', '=', 'hkp.id_sub_materi')
            ->where('hkp.id_paket', $id_paket)
            ->select(
                'sm.id_sub_materi',
                'sm.nama_sub_materi',
                'hkp.status_kemampuan',
                DB::raw('COUNT(*) AS jumlah')
            )
            ->groupBy('sm.id_sub_materi', 'sm.nama_sub_materi', 'hkp.status_kemampuan')
            ->get();

        // Susun per sub materi
        $penguasaanSubMateri = $statusPerSubMateri
            ->groupBy('id_sub_materi')
            ->map(function ($rows) use ($jumlahPeserta) {
                $first  = $rows->first();
                $lemah  = $rows->firstWhere('status_kemampuan', 'Lemah')?->jumlah  ?? 0;
                $normal = $rows->firstWhere('status_kemampuan', 'Normal')?->jumlah ?? 0;
                $kuat   = $rows->firstWhere('status_kemampuan', 'Kuat')?->jumlah   ?? 0;
                $total  = $lemah + $normal + $kuat;

                return [
                    'id_sub_materi'   => $first->id_sub_materi,
                    'nama_sub_materi' => $first->nama_sub_materi,
                    'jumlah_peserta'  => $total,
                    'lemah'  => ['jumlah' => $lemah,  'persen' => $total > 0 ? round($lemah  / $total * 100, 1) : 0],
                    'normal' => ['jumlah' => $normal, 'persen' => $total > 0 ? round($normal / $total * 100, 1) : 0],
                    'kuat'   => ['jumlah' => $kuat,   'persen' => $total > 0 ? round($kuat   / $total * 100, 1) : 0],
                ];
            })
            ->values();

        // ── 5. Peringkat Peserta ───────────────────────────────────────────────
        // Urutkan berdasarkan total_skor_akhir desc, sertakan rata-rata theta
        $rataRataThetaPerPeserta = DB::table('hasil_kemampuan_peserta')
            ->where('id_paket', $id_paket)
            ->select('id_peserta', DB::raw('AVG(theta) AS rata_rata_theta'))
            ->groupBy('id_peserta')
            ->get()
            ->keyBy('id_peserta');

        $peringkatPeserta = DB::table('ringkasan_akhir_ujian as rau')
            ->join('peserta as p', 'p.id_peserta', '=', 'rau.id_peserta')
            ->where('rau.id_paket', $id_paket)
            ->select(
                'p.id_peserta',
                'p.nama_peserta',
                'rau.total_skor_akhir',
                'rau.rata_rata_prob'
            )
            ->orderByDesc('rau.total_skor_akhir')
            ->get()
            ->map(function ($p, $index) use ($rataRataThetaPerPeserta) {
                $p->peringkat        = $index + 1;
                $p->rata_rata_theta  = round($rataRataThetaPerPeserta[$p->id_peserta]->rata_rata_theta ?? 0, 6);
                return $p;
            });

        // ── Build response ─────────────────────────────────────────────────────
        return response()->json([
            'success' => true,
            'message' => 'Dashboard admin berhasil diambil',
            'data'    => [

                'ringkasan_paket' => [
                    'id_paket'            => $paket->id_paket,
                    'nama_paket'          => $paket->nama_paket,
                    'jumlah_peserta'      => $jumlahPeserta,
                    'rata_rata_skor'      => round($ringkasanPaket->rata_rata_skor_akhir ?? 0, 2),
                    'rata_rata_prob'      => round($ringkasanPaket->rata_rata_prob       ?? 0, 2),
                    'rata_rata_theta'     => round($rataRataTheta                        ?? 0, 6),
                    'skor_tertinggi'      => $ringkasanPaket->skor_tertinggi ?? 0,
                    'skor_terendah'       => $ringkasanPaket->skor_terendah  ?? 0,
                ],

                'distribusi_kemampuan' => $distribusiKemampuan,

                'analisis_soal' => [
                    'rekap_kategori' => $rekapKategori,
                    'detail'         => $analisisSoal,
                ],

                'penguasaan_sub_materi' => $penguasaanSubMateri,

                'peringkat_peserta' => $peringkatPeserta,
            ],
        ]);
    }
}
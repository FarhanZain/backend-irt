<?php

namespace App\Http\Controllers;

use App\Models\PaketSoal;
use App\Models\IrtPeserta;
use App\Models\IrtSoal;
use App\Models\Topik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IrtPengembangController extends Controller
{
    public function showDashboardHasil($id_paket)
    {
        // Ambil paket dasar
        $paket = PaketSoal::findOrFail($id_paket);

        // ==========================================
        // SECTION 1: RINGKASAN STATISTIK
        // ==========================================
        // Mengambil data unik peserta dari irt_peserta yang mengambil paket ini
        $ringkasanPeserta = IrtPeserta::where('paket_id', $id_paket)
            ->selectRaw('
                COUNT(DISTINCT user_id) as jumlah_peserta,
                ROUND(AVG(skor), 2) as rata_rata_skor,
                ROUND(AVG(theta), 3) as rata_rata_theta,
                MAX(skor) as skor_tertinggi,
                MIN(skor) as skor_terendah
            ')->first();

        // Jika belum ada data IRT diolah
        $jumlahPeserta = $ringkasanPeserta->jumlah_peserta ?? 0;

        // ==========================================
        // SECTION 2: GRAFIK CHART DISTRIBUSI (Theta & Skor)
        // ==========================================
        // --- DINAMIS 5 BAGIAN UNTUK DISTRIBUSI THETA ---

        // 1. Ambil nilai theta minimum dan maksimum dari database
        $minTheta = IrtPeserta::where('paket_id', $id_paket)->min('theta') ?? -3.0;
        $maxTheta = IrtPeserta::where('paket_id', $id_paket)->max('theta') ?? 3.0;

        // 2. Hitung jarak/rentang total lalu bagi menjadi 5 bagian
        $rentangTheta = $maxTheta - $minTheta;
        $stepTheta = $rentangTheta / 5;

        // 3. Definisikan 5 struktur bucket/keranjang untuk Theta
        $bucketsTheta = [];
        for ($i = 0; $i < 5; $i++) {
            $min = $minTheta + ($i * $stepTheta);
            $max = $minTheta + (($i + 1) * $stepTheta);
            
            // Membulatkan label menjadi 2 desimal agar rapi di chart Next.js
            $label = ROUND($min, 2) . ' s/d ' . ROUND($max, 2);
            
            $bucketsTheta[] = [
                'label' => $label,
                'min' => $min,
                'max' => $max,
                'jumlah' => 0
            ];
        }

        // 4. Ambil semua nilai theta rata-rata per user di paket ini
        $semuaThetaPeserta = IrtPeserta::where('paket_id', $id_paket)
            ->select('user_id', 'theta')
            ->get()
            ->groupBy('user_id')
            ->map(fn($group) => $group->first()->theta);

        // 5. Masukkan theta peserta ke dalam 5 keranjang yang sesuai
        foreach ($semuaThetaPeserta as $theta) {
            foreach ($bucketsTheta as $key => $bucket) {
                // Gunakan batasan inklusif, khusus untuk indeks terakhir pastikan meng-cover nilai max tepat
                if ($theta >= $bucket['min'] && ($theta <= $bucket['max'] || $key == 4)) {
                    $bucketsTheta[$key]['jumlah']++;
                    break;
                }
            }
        }

        // 6. Pecah ke format categories dan values untuk Next.js
        $categoriesTheta = array_column($bucketsTheta, 'label');
        $valuesTheta = array_column($bucketsTheta, 'jumlah');

        // --- DINAMIS 5 BAGIAN UNTUK DISTRIBUSI SKOR ---

        // 1. Ambil skor tertinggi dari paket ini sebagai acuan limit atas
        $maxSkor = IrtPeserta::where('paket_id', $id_paket)->max('skor') ?? 100;

        // 2. Hitung ukuran range untuk tiap bagian (misal: 100/5 = 20, atau 1000/5 = 200)
        $step = ceil($maxSkor / 5);

        // 3. Definisikan 5 struktur bucket/keranjang interval awal
        $buckets = [];
        for ($i = 0; $i < 5; $i++) {
            $min = ($i * $step) + ($i > 0 ? 1 : 0); // Jika i > 0, mulai dari kelipatan + 1 (misal: 21, 41, atau 201)
            $max = ($i + 1) * $step;
            
            $buckets[] = [
                'label' => $min . '-' . $max,
                'min' => $min,
                'max' => $max,
                'jumlah' => 0
            ];
        }

        // 4. Ambil semua skor peserta unik di paket ini
        $semuaSkorPeserta = IrtPeserta::where('paket_id', $id_paket)
            ->select('user_id', 'skor')
            ->get()
            ->groupBy('user_id')
            ->map(fn($group) => $group->first()->skor); // Mengambil skor per user jika datanya duplikat per topik

        // 5. Masukkan skor peserta ke dalam 5 keranjang yang sesuai
        foreach ($semuaSkorPeserta as $skor) {
            foreach ($buckets as $key => $bucket) {
                // Cek apakah skor masuk dalam range bucket ini
                if ($skor >= $bucket['min'] && $skor <= $bucket['max']) {
                    $buckets[$key]['jumlah']++;
                    break;
                }
            }
        }

        // 6. Pecah ke format categories dan values untuk Next.js
        $categoriesSkor = array_column($buckets, 'label');
        $valuesSkor = array_column($buckets, 'jumlah');

        // ==========================================
        // SECTION 3: PENGUASAAN TOPIK
        // ==========================================
        $listTopik = Topik::where('paket_id', $id_paket)->get();
        $penguasaanSubMateri = [];

        foreach ($listTopik as $topik) {
            // Ambil total peserta unik per sub-materi ini
            $totalPesertaTopik = IrtPeserta::where('topik_id', $topik->id_topik)->count();

            // Hitung jumlah masing-masing kategori status_kemampuan
            $lemahCount  = IrtPeserta::where('topik_id', $topik->id_topik)->where('status_kemampuan', 'lemah')->count();
            $normalCount = IrtPeserta::where('topik_id', $topik->id_topik)->where('status_kemampuan', 'normal')->count();
            $kuatCount   = IrtPeserta::where('topik_id', $topik->id_topik)->where('status_kemampuan', 'kuat')->count();

            $penguasaanSubMateri[] = [
                'id_sub_materi'   => $topik->id_topik,
                'nama_sub_materi' => $topik->nama_topik,
                'jumlah_peserta'  => $totalPesertaTopik,
                'lemah' => [
                    'jumlah' => $lemahCount,
                    'persen' => $totalPesertaTopik > 0 ? ROUND(($lemahCount / $totalPesertaTopik) * 100, 1) : 0
                ],
                'normal' => [
                    'jumlah' => $normalCount,
                    'persen' => $totalPesertaTopik > 0 ? ROUND(($normalCount / $totalPesertaTopik) * 100, 1) : 0
                ],
                'kuat' => [
                    'jumlah' => $kuatCount,
                    'persen' => $totalPesertaTopik > 0 ? ROUND(($kuatCount / $totalPesertaTopik) * 100, 1) : 0
                ]
            ];
        }

        // ==========================================
        // SECTION 4: ANALISIS KARAKTERISTIK SOAL
        // ==========================================
        $mudahCount  = IrtSoal::where('paket_id', $id_paket)->where('kategori_kesulitan', 'mudah')->count();
        $sedangCount = IrtSoal::where('paket_id', $id_paket)->where('kategori_kesulitan', 'sedang')->count();
        $susahCount  = IrtSoal::where('paket_id', $id_paket)->where('kategori_kesulitan', 'susah')->count();

        // Rekap kategori daya pembeda soal (Tambahan Baru)
        $sangatBurukCount = IrtSoal::where('paket_id', $id_paket)->where('kategori_daya_pembeda', 'sangat buruk')->count();
        $burukCount       = IrtSoal::where('paket_id', $id_paket)->where('kategori_daya_pembeda', 'buruk')->count();
        $cukupCount       = IrtSoal::where('paket_id', $id_paket)->where('kategori_daya_pembeda', 'cukup')->count();
        $baikCount        = IrtSoal::where('paket_id', $id_paket)->where('kategori_daya_pembeda', 'baik')->count();
        $sangatBaikCount  = IrtSoal::where('paket_id', $id_paket)->where('kategori_daya_pembeda', 'sangat baik')->count();

        // Detail karakteristik item per butir soal
        $detailSoalIrt = IrtSoal::with(['soal.topik'])
            ->where('paket_id', $id_paket)
            ->get()
            ->map(function ($item, $index) {
                return [
                    'no'                     => $index + 1,
                    'topik_soal'             => $item->soal->topik->nama_topik ?? '-',
                    'pertanyaan_soal'        => $item->soal->pertanyaan ?? '-',
                    'tingkat_kesulitan_soal' => ROUND($item->tingkat_kesulitan, 3),
                    'kategori_kesulitan_soal'=> $item->kategori_kesulitan,
                    'daya_pembeda'           => ROUND($item->daya_pembeda, 3),
                    'kategori_daya_pembeda'  => $item->kategori_daya_pembeda,
                    'tebakan'                => ROUND($item->tebakan, 3),
                    'model_irt'              => $item->model_irt ?? '1PL/2PL/3PL'
                ];
            });

        // ==========================================
        // SECTION 5: PERINGKAT PESERTA (BERDASARKAN THETA GLOBAL)
        // ==========================================
        // Kita merata-ratakan nilai theta global peserta jika ia memiliki baris multi-topik di paket tersebut
        $peringkatPeserta = IrtPeserta::with(['user'])
            ->where('paket_id', $id_paket)
            ->select(
                'user_id', 
                DB::raw('ROUND(AVG(skor), 2) as avg_skor'), // Menggunakan AVG dan dibulatkan 2 angka desimal
                DB::raw('AVG(theta) as avg_theta')
            )
            ->groupBy('user_id')
            ->orderBy('avg_theta', 'desc')
            ->get()
            ->map(function ($p, $index) {
                return [
                    'peringkat'    => $index + 1,
                    'nama_peserta' => $p->user->nama ?? 'Anonim',
                    'total_skor'   => $p->avg_skor, // Sekarang berisi nilai rata-rata skor
                    'nilai_theta'  => ROUND($p->avg_theta, 3)
                ];
            });

        // ==========================================
        // BUNDLING FORMAT RESPONSE SESUAI NEXT.JS
        // ==========================================
        return response()->json([
            'status' => 'success',
            'detailPaket' => [
                'ringkasan_paket' => [
                    'nama_paket'     => $paket->nama_paket,
                    'jumlah_peserta' => $jumlahPeserta,
                    'rata_rata_skor' => $ringkasanPeserta->rata_rata_skor ?? 0,
                    'rata_rata_theta'=> $ringkasanPeserta->rata_rata_theta ?? 0,
                    'skor_tertinggi' => $ringkasanPeserta->skor_tertinggi ?? 0,
                    'skor_terendah'  => $ringkasanPeserta->skor_terendah ?? 0,
                ],
                'categoriesTheta'       => $categoriesTheta,
                'valuesTheta'           => $valuesTheta,
                'categoriesSkor'        => $categoriesSkor,
                'valuesSkor'            => $valuesSkor,
                'penguasaan_sub_materi' => $penguasaanSubMateri,
                'analisis_soal' => [
                    'kategori_kesulitan' => [
                        'mudah'  => $mudahCount,
                        'sedang' => $sedangCount,
                        'susah'  => $susahCount,
                    ],
                    'kategori_daya_pembeda' => [ // Struktur response baru untuk Next.js
                        'sangat_buruk' => $sangatBurukCount,
                        'buruk'        => $burukCount,
                        'cukup'        => $cukupCount,
                        'baik'         => $baikCount,
                        'sangat_baik'  => $sangatBaikCount,
                    ],
                    'detail' => $detailSoalIrt
                ],
                'peringkat_peserta' => $peringkatPeserta
            ]
        ]);
    }
}
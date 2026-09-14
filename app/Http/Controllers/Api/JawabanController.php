<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class JawabanController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════════════
    // API 1 – Jawaban Detail Per Peserta
    // GET /api/jawaban/{id_paket}/{id_peserta}
    //
    // Response:
    //   - id_peserta, nama_peserta
    //   - id_paket, nama_paket
    //   - list soal beserta jawaban peserta tersebut
    // ═══════════════════════════════════════════════════════════════════════════

    public function perPeserta(int $id_paket, int $id_peserta): JsonResponse
    {
        // ── Validasi ───────────────────────────────────────────────────────────
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

        // ── Ambil semua soal dalam paket + jawaban peserta (LEFT JOIN) ─────────
        // LEFT JOIN supaya soal yang tidak dijawab tetap muncul (null = kosong)
        $soalDenganJawaban = DB::table('soal as s')
            ->join('sub_materi as sm', 'sm.id_sub_materi', '=', 's.id_sub_materi')
            ->leftJoin('hasil_ujian as hu', function ($join) use ($id_peserta) {
                $join->on('hu.id_soal', '=', 's.id_soal')
                     ->where('hu.id_peserta', '=', $id_peserta);
            })
            ->where('s.id_paket_soal', $id_paket)
            ->select(
                's.id_soal',
                's.nomor_soal',
                'sm.id_sub_materi',
                'sm.nama_sub_materi',
                'hu.jawaban'
            )
            ->orderBy('s.nomor_soal')
            ->get()
            ->map(function ($soal) {
                return [
                    'id_soal'          => (int) $soal->id_soal,
                    'nomor_soal'       => (int) $soal->nomor_soal,
                    'id_sub_materi'    => (int) $soal->id_sub_materi,
                    'nama_sub_materi'  => $soal->nama_sub_materi,
                    'jawaban'          => isset($soal->jawaban) ? (int) $soal->jawaban : null,
                    'keterangan'       => match (isset($soal->jawaban) ? (int) $soal->jawaban : null) {
                        1       => 'Benar',
                        0       => 'Salah',
                        default => 'Kosong',
                    },
                ];
            });

        // ── Rekap statistik ────────────────────────────────────────────────────
        $jumlahBenar  = $soalDenganJawaban->where('jawaban', 1)->count();
        $jumlahSalah  = $soalDenganJawaban->where('jawaban', 0)->count();
        $jumlahKosong = $soalDenganJawaban->whereNull('jawaban')->count();

        return response()->json([
            'success' => true,
            'message' => 'Data jawaban peserta berhasil diambil',
            'data'    => [
                'id_peserta'   => $peserta->id_peserta,
                'nama_peserta' => $peserta->nama_peserta,
                'id_paket'     => $paket->id_paket,
                'nama_paket'   => $paket->nama_paket,
                'rekap' => [
                    'total_soal'    => $soalDenganJawaban->count(),
                    'jumlah_benar'  => $jumlahBenar,
                    'jumlah_salah'  => $jumlahSalah,
                    'jumlah_kosong' => $jumlahKosong,
                ],
                'soal' => $soalDenganJawaban->values(),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // API 2 – Jawaban Semua Peserta Per Paket
    // GET /api/jawaban/{id_paket}
    //
    // Response:
    //   - id_paket, nama_paket
    //   - list peserta, tiap peserta berisi list soal + jawabannya
    // ═══════════════════════════════════════════════════════════════════════════

    public function semuaPeserta(int $id_paket): JsonResponse
    {
        // ── Validasi ───────────────────────────────────────────────────────────
        $paket = DB::table('paket_soal')->where('id_paket', $id_paket)->first();
        if (! $paket) {
            return response()->json([
                'success' => false,
                'message' => "Paket soal dengan id {$id_paket} tidak ditemukan",
            ], 404);
        }

        // ── Ambil semua soal dalam paket (1 query, dipakai sebagai acuan) ──────
        $semuaSoal = DB::table('soal as s')
            ->join('sub_materi as sm', 'sm.id_sub_materi', '=', 's.id_sub_materi')
            ->where('s.id_paket_soal', $id_paket)
            ->select('s.id_soal', 's.nomor_soal', 'sm.id_sub_materi', 'sm.nama_sub_materi')
            ->orderBy('s.nomor_soal')
            ->get();

        // ── Ambil semua jawaban untuk paket ini sekaligus (1 query) ───────────
        // Lalu di-group by id_peserta → efisien, tidak ada N+1
        $semuaJawaban = DB::table('hasil_ujian as hu')
            ->join('soal as s', 's.id_soal', '=', 'hu.id_soal')
            ->where('s.id_paket_soal', $id_paket)
            ->select('hu.id_peserta', 'hu.id_soal', 'hu.jawaban')
            ->get()
            ->groupBy('id_peserta'); // ['id_peserta' => Collection of jawaban]

        // ── Ambil semua peserta yang mengerjakan paket ini ─────────────────────
        $semuaPeserta = DB::table('peserta as p')
            ->join('hasil_ujian as hu', 'hu.id_peserta', '=', 'p.id_peserta')
            ->join('soal as s', 's.id_soal', '=', 'hu.id_soal')
            ->where('s.id_paket_soal', $id_paket)
            ->select('p.id_peserta', 'p.nama_peserta')
            ->distinct()
            ->orderBy('p.id_peserta')
            ->get();

        // ── Susun data per peserta ─────────────────────────────────────────────
        $dataPeserta = $semuaPeserta->map(function ($peserta) use ($semuaSoal, $semuaJawaban) {
            // Jawaban peserta ini, di-index by id_soal untuk lookup cepat
            $jawabanPeserta = ($semuaJawaban->get($peserta->id_peserta) ?? collect())
                ->keyBy('id_soal');

            // Map semua soal + jawaban peserta ini
            $soalList = $semuaSoal->map(function ($soal) use ($jawabanPeserta) {
                $jawaban = $jawabanPeserta->get($soal->id_soal);
                $nilaiJawaban = isset($jawaban) ? (int) $jawaban->jawaban : null;

                return [
                    'id_soal'         => (int) $soal->id_soal,
                    'nomor_soal'      => (int) $soal->nomor_soal,
                    'id_sub_materi'   => (int) $soal->id_sub_materi,
                    'nama_sub_materi' => $soal->nama_sub_materi,
                    'jawaban'         => $nilaiJawaban,
                    'keterangan'      => match ($nilaiJawaban) {
                        1       => 'Benar',
                        0       => 'Salah',
                        default => 'Kosong',
                    },
                ];
            });

            $jumlahBenar  = $soalList->where('jawaban', 1)->count();
            $jumlahSalah  = $soalList->where('jawaban', 0)->count();
            $jumlahKosong = $soalList->whereNull('jawaban')->count();

            return [
                'id_peserta'   => $peserta->id_peserta,
                'nama_peserta' => $peserta->nama_peserta,
                'rekap' => [
                    'total_soal'    => $soalList->count(),
                    'jumlah_benar'  => $jumlahBenar,
                    'jumlah_salah'  => $jumlahSalah,
                    'jumlah_kosong' => $jumlahKosong,
                ],
                'soal' => $soalList->values(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Data jawaban semua peserta berhasil diambil',
            'data'    => [
                'id_paket'      => $paket->id_paket,
                'nama_paket'    => $paket->nama_paket,
                'total_peserta' => $semuaPeserta->count(),
                'peserta'       => $dataPeserta->values(),
            ],
        ]);
    }
}
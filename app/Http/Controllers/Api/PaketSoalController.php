<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class PaketSoalController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════════════
    // HALAMAN 1 – Daftar Paket Soal
    // GET /api/v1/paket-soal
    // ═══════════════════════════════════════════════════════════════════════════

    public function index(): JsonResponse
    {
        $paketSoal = DB::table('paket_soal as p')
            ->select(
                'p.id_paket',
                'p.nama_paket',
                DB::raw('COUNT(DISTINCT s.id_soal) as jumlah_soal'),
                DB::raw('COUNT(DISTINCT sm.id_sub_materi) as jumlah_sub_materi')
            )
            ->leftJoin('soal as s', 's.id_paket_soal', '=', 'p.id_paket')
            ->leftJoin('sub_materi as sm', 'sm.id_paket_soal', '=', 'p.id_paket')
            ->groupBy('p.id_paket', 'p.nama_paket')
            ->orderBy('p.id_paket')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar paket soal berhasil diambil',
            'data'    => $paketSoal,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HALAMAN 2 – Detail Paket + Info Peserta + Cek Ketersediaan Hasil IRT
    // GET /api/v1/paket-soal/{id_paket}/detail/{id_peserta}
    //
    // Response mencakup:
    //   - nama_paket
    //   - jumlah_soal
    //   - sub_materi  (id, nama, jumlah soal)
    //   - peserta     (id, nama)
    //   - irt_sudah_ada  → boolean, dipakai untuk conditional rendering button
    // ═══════════════════════════════════════════════════════════════════════════

    public function detail(int $id_paket, int $id_peserta): JsonResponse
    {
        // ── 1. Validasi paket ──────────────────────────────────────────────────
        $paket = DB::table('paket_soal')
            ->where('id_paket', $id_paket)
            ->first();

        if (! $paket) {
            return response()->json([
                'success' => false,
                'message' => "Paket soal dengan id {$id_paket} tidak ditemukan",
            ], 404);
        }

        // ── 2. Validasi peserta ────────────────────────────────────────────────
        $peserta = DB::table('peserta')
            ->where('id_peserta', $id_peserta)
            ->first();

        if (! $peserta) {
            return response()->json([
                'success' => false,
                'message' => "Peserta dengan id {$id_peserta} tidak ditemukan",
            ], 404);
        }

        // ── 3. Jumlah soal dalam paket ─────────────────────────────────────────
        $jumlahSoal = DB::table('soal')
            ->where('id_paket_soal', $id_paket)
            ->count();

        // ── 4. Sub materi beserta jumlah soal tiap sub materi ─────────────────
        $subMateri = DB::table('sub_materi as sm')
            ->leftJoin('soal as s', function ($join) use ($id_paket) {
                $join->on('s.id_sub_materi', '=', 'sm.id_sub_materi')
                     ->where('s.id_paket_soal', '=', $id_paket);
            })
            ->where('sm.id_paket_soal', $id_paket)
            ->select(
                'sm.id_sub_materi',
                'sm.nama_sub_materi',
                DB::raw('COUNT(s.id_soal) AS jumlah_soal')
            )
            ->groupBy('sm.id_sub_materi', 'sm.nama_sub_materi')
            ->orderBy('sm.id_sub_materi')
            ->get();

        // ── 5. Cek apakah hasil IRT sudah tersedia untuk peserta ini ──────────
        // Cukup cek satu baris di hasil_kemampuan_peserta
        $irtSudahAda = DB::table('hasil_kemampuan_peserta')
            ->where('id_paket', $id_paket)
            ->where('id_peserta', $id_peserta)
            ->exists();

        return response()->json([
            'success' => true,
            'message' => 'Detail paket berhasil diambil',
            'data'    => [
                'paket' => [
                    'id_paket'   => $paket->id_paket,
                    'nama_paket' => $paket->nama_paket,
                ],
                'peserta' => [
                    'id_peserta'   => $peserta->id_peserta,
                    'nama_peserta' => $peserta->nama_peserta,
                ],
                'jumlah_soal'  => $jumlahSoal,
                'jumlah_sub_materi' => $subMateri->count(),
                'sub_materi'   => $subMateri,
                'irt_sudah_ada' => $irtSudahAda, // ← conditional rendering button Next.js
            ],
        ]);
    }
}
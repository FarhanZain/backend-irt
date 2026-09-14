<?php

namespace App\Http\Controllers;

use App\Models\UjianPeserta;
use App\Models\PaketSoal;
use App\Models\JawabanPeserta;
use App\Models\JawabanSoal;
use App\Models\Soal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class UjianPesertaController extends Controller
{
    // Helper mendapatkan User ID (sesuaikan jika memakai Sanctum: $request->user()->id)
    private function getUserId(Request $request)
    {
        // 1. Cek dari Custom Middleware (jika token di-decode ke attributes)
        if ($request->attributes->has('user_id')) {
            return $request->attributes->get('user_id');
        }

        // 2. Cek dari Laravel Auth Auth (Sanctum / Passport)
        if (Auth::check()) {
            return Auth::id();
        }
        if ($request->user()) {
            return $request->user()->id;
        }

        // 3. Cadangan Khusus GET: Ambil dari Query Parameter (?user_id=xx) atau Request Body
        if ($request->has('user_id')) {
            return $request->input('user_id');
        }

        return null;
    }

    /**
     * 1. Mengambil semua paket beserta status ujian peserta (Sesuai Request)
     */
    public function index(Request $request)
    {
        try {
            $userId = $this->getUserId($request);

            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User ID tidak terdeteksi oleh server. Pastikan Header Authorization atau parameter user_id dikirim.'
                ], 401);
            }

            // Ambil semua paket soal dengan hitungan topik & soal
            // Serta eager load tabel ujian_peserta yang di-filter khusus untuk user ini
            $paket = PaketSoal::withCount(['topik', 'soal'])
                ->with(['ujianPeserta' => function($q) use ($userId) {
                    $q->where('user_id', $userId);
                }])->get();

            foreach ($paket as $p) {
                // Ambal data pengerjaan jika ada
                $ujian = $p->ujianPeserta->first();

                // Logika Aturan: Jika data ADA -> 'sudah_selesai', Jika TIDAK ADA -> 'belum_mengerjakan'
                if ($ujian) {
                    $p->status_pengerjaan = 'sudah_selesai';
                    $p->id_ujian = $ujian->id_ujian;
                } else {
                    $p->status_pengerjaan = 'belum_mengerjakan';
                    $p->id_ujian = null;
                }

                // --- TAMBAHAN CEK TABEL IRT PESERTA ---
                // Memeriksa apakah ada baris data berdasarkan user_id dan paket_id di tabel irt_peserta
                // $cekIrt = DB::table('irt_peserta')
                //     ->where('user_id', $userId)
                //     ->where('paket_id', $p->id_paket)
                //     ->exists();

                // $p->status_hasil = $cekIrt ? 'sudah ada hasil' : 'tidak ada hasil';

                // Sembunyikan relasi mentah agar response JSON rapi dan bersih
                $p->makeHidden('ujianPeserta');
            }

            return response()->json($paket, 200);

        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // 2. Mengambil detail informasi paket sebelum masuk ruang ujian
    public function showDetailPaket($id_paket, Request $request)
    {
        try {
            // Ambil ID User secara dinamis (dari header token / custom middleware / query string)
            $userId = $this->getUserId($request); 

            if (!$userId) {
                return response()->json([
                    'status' => 'error', 
                    'message' => 'User ID tidak terdeteksi oleh server. Pastikan token atau user_id dikirim dengan benar.'
                ], 401);
            }

            // Ambil paket berdasarkan ID beserta sub-topik dan hitungan jumlah soal per topik
            $paket = PaketSoal::where('id_paket', $id_paket)
                ->with(['topik' => function($q) {
                    $q->withCount('soal');
                }])->withCount('soal')->firstOrFail();

            // Cek keberadaan data user ini di tabel ujian_peserta untuk paket terkait
            $statusUjian = UjianPeserta::where('user_id', $userId)
                ->where('paket_id', $id_paket)
                ->first();

            // LOGIKA ATURAN BARU:
            // Jika data ditemukan -> 'sudah_selesai'
            // Jika data TIDAK ditemukan -> 'belum_mengerjakan'
            $status = 'belum_mengerjakan';
            $idUjian = null;

            if ($statusUjian) {
                $status = 'sudah_selesai';
                $idUjian = $statusUjian->id_ujian;
            }

            // --- TAMBAHAN CEK TABEL IRT PESERTA ---
            $cekIrtDetail = DB::table('irt_peserta')
                ->where('user_id', $userId)
                ->where('paket_id', $id_paket)
                ->exists();

            $statusHasil = $cekIrtDetail ? 'sudah ada hasil' : 'tidak ada hasil';

            return response()->json([
                'debug_user_id_terbaca' => $userId, // Membantu pelacakan validasi identitas user
                'paket' => $paket,
                'jumlah_topik' => $paket->topik->count(),
                'jumlah_soal_keseluruhan' => $paket->soal_count,
                'status_pengerjaan' => $status,
                'status_hasil' => $statusHasil,
                'id_ujian' => $idUjian
            ], 200);

        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // 3. Ambil Bank Soal Kosongan (Ditambah Proteksi Ketat)
    public function mulaiUjian(Request $request)
    {
        try {
            $request->validate([
                'paket_id' => 'required|exists:paket_soal,id_paket'
            ]);

            $userId = $this->getUserId($request);
            $paketId = $request->paket_id;

            // PROTEKSI: Jika di database user sudah punya record 'waktu_selesai', BLOKIR!
            $cekSelesai = UjianPeserta::where('user_id', $userId)
                ->where('paket_id', $paketId)
                ->whereNotNull('waktu_selesai')
                ->first();

            if ($cekSelesai) {
                return response()->json([
                    'status' => 'forbidden',
                    'message' => 'Anda sudah menyelesaikan ujian ini dan tidak dapat masuk kembali.'
                ], 403); // Return 403 Forbidden
            }

            $relationName = method_exists(new Soal(), 'jawabanSoal') ? 'jawabanSoal' : 'jawaban';

            $soalList = Soal::whereHas('topik', function($q) use ($paketId) {
                    $q->where('paket_id', $paketId);
                })
                ->with([$relationName => function($j) { 
                    $j->select('id_jawaban_soal', 'soal_id', 'teks_jawaban');
                }, 'topik:id_topik,nama_topik'])
                ->get()
                ->map(function($soal) use ($relationName) {
                    $soal->jawaban_terpilih_id = null;
                    if ($relationName !== 'jawaban') {
                        $soal->jawaban = $soal->{$relationName};
                    }
                    return $soal;
                });

            return response()->json([
                'soal' => $soalList
            ], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // 4. Kirim dan Simpan Sekaligus Lembar Jawaban (Saat Tombol Selesai Di-klik)
    public function submitSemuaJawaban(Request $request)
    {
        // Gunakan Database Transaction agar jika satu baris gagal, data tidak rusak
        DB::beginTransaction();
        try {
            $request->validate([
                'paket_id' => 'required|exists:paket_soal,id_paket',
                'jawaban_array' => 'present|array' // array berisi data [{soal_id, jawaban_soal_id}]
            ]);

            $userId = $this->getUserId($request);
            $paketId = $request->paket_id;

            // 1. Catat atau update sesi ujian di tabel ujian_peserta
            $ujian = UjianPeserta::updateOrCreate(
                [
                    'user_id' => $userId,
                    'paket_id' => $paketId
                ],
                [
                    'waktu_mulai' => Carbon::now()->subMinutes(30), // Fallback waktu mulai
                    'waktu_selesai' => Carbon::now() // Mengunci status menjadi selesai
                ]
            );

            // Hapus jawaban lama jika sebelumnya ada record gantung (pembersihan data)
            JawabanPeserta::where('ujian_id', $ujian->id_ujian)->delete();

            // 2. Loop dan simpan massal seluruh lembar jawaban dari frontend
            foreach ($request->jawaban_array as $item) {
                if (!isset($item['jawaban_soal_id']) || json_decode($item['jawaban_soal_id']) === null) {
                    continue; // Lewati jika nomor soal tersebut dikosongkan oleh peserta
                }

                // Cek kunci jawaban untuk menentukan nilai is_benar (1 atau 0)
                $kunci = JawabanSoal::where('id_jawaban_soal', $item['jawaban_soal_id'])
                    ->where('soal_id', $item['soal_id'])
                    ->first();

                $isBenar = 0;
                if ($kunci) {
                    $isBenar = ($kunci->is_kunci_jawaban == 1 || $kunci->is_kunci_jawaban == true) ? 1 : 0;
                }

                // Insert data lembar jawaban baru
                JawabanPeserta::create([
                    'ujian_id' => $ujian->id_ujian,
                    'soal_id' => $item['soal_id'],
                    'jawaban_soal_id' => $item['jawaban_soal_id'],
                    'is_benar' => $isBenar
                ]);
            }

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Seluruh lembar ujian Anda berhasil disimpan dan dikunci permanen!'
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
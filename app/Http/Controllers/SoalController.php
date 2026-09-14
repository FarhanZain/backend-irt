<?php

namespace App\Http\Controllers;

use App\Models\Soal;
use App\Models\JawabanSoal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SoalController extends Controller
{
    // List soal di dalam topik beserta opsi jawabannya
    public function getSoalByTopik($topik_id)
    {
        $soal = Soal::where('topik_id', $topik_id)
            ->with('jawaban')
            ->orderBy('topik_id', 'desc')
            ->get();

        return response()->json(['status' => 'success', 'data' => $soal]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'topik_id' => 'required|exists:topik,id_topik',
            'pertanyaan' => 'required|string',
            'pilihan' => 'required|array|min:4|max:4',
            'pilihan.*.teks_jawaban' => 'required|string',
            'kunci_indeks' => 'required|integer|min:0|max:3' // Indeks pilihan (0-3) yang menjadi kunci
        ]);

        try {
            DB::beginTransaction();

            // 1. Simpan Pertanyaan
            $soal = Soal::create([
                'topik_id' => $request->topik_id,
                'pertanyaan' => $request->pertanyaan
            ]);

            // 2. Simpan 4 Pilihan Jawaban
            foreach ($request->pilihan as $index => $item) {
                JawabanSoal::create([
                    'soal_id' => $soal->id_soal,
                    'teks_jawaban' => $item['teks_jawaban'],
                    'is_kunci_jawaban' => ($index == $request->kunci_indeks) ? true : false
                ]);
            }

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Soal dan pilihan jawaban berhasil disimpan!'], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Gagal menyimpan soal: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $soal = Soal::findOrFail($id);
        $soal->delete(); // Cascade delete akan menghapus baris di jawaban_soal otomatis
        return response()->json(['status' => 'success', 'message' => 'Soal berhasil dihapus!']);
    }

    public function update(Request $request, $id)
    {
        $soal = Soal::findOrFail($id);

        $request->validate([
            'pertanyaan' => 'required|string',
            'pilihan' => 'required|array|min:4|max:4',
            'pilihan.*.id_jawaban_soal' => 'required|exists:jawaban_soal,id_jawaban_soal',
            'pilihan.*.teks_jawaban' => 'required|string',
            'kunci_indeks' => 'required|integer|min:0|max:3'
        ]);

        try {
            DB::beginTransaction();

            // 1. Update Teks Pertanyaan
            $soal->update([
                'pertanyaan' => $request->pertanyaan
            ]);

            // 2. Update 4 Pilihan Jawaban berdasarkan ID-nya masing-masing
            foreach ($request->pilihan as $index => $item) {
                JawabanSoal::where('id_jawaban_soal', $item['id_jawaban_soal'])->update([
                    'teks_jawaban' => $item['teks_jawaban'],
                    'is_kunci_jawaban' => ($index == $request->kunci_indeks) ? true : false
                ]);
            }

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Butir soal dan kunci jawaban berhasil diperbarui!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Gagal memperbarui soal: ' . $e->getMessage()], 500);
        }
    }
}
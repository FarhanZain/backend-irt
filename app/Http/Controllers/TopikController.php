<?php

namespace App\Http\Controllers;

use App\Models\Topik;
use Illuminate\Http\Request;

class TopikController extends Controller
{
    // Mengambil semua topik berdasarkan ID paket tertentu + Menghitung jumlah soal di dalamnya
    public function getTopikByPaket(Request $request, $paket_id)
    {
        $perPage = $request->get('per_page', 5);
        $topik = Topik::where('paket_id', $paket_id)
            ->withCount('soal')
            ->orderBy('id_topik', 'asc')
            ->paginate($perPage);

        return response()->json($topik);
    }

    public function store(Request $request)
    {
        $validasi = $request->validate([
            'paket_id' => 'required|exists:paket_soal,id_paket',
            'nama_topik' => 'required|string|max:255'
        ]);

        $topik = Topik::create($validasi);
        return response()->json(['status' => 'success', 'message' => 'Topik berhasil ditambahkan!', 'data' => $topik], 201);
    }

    public function update(Request $request, $id)
    {
        $topik = Topik::findOrFail($id);
        $validasi = $request->validate([
            'nama_topik' => 'required|string|max:255'
        ]);

        $topik->update($validasi);
        return response()->json(['status' => 'success', 'message' => 'Nama topik berhasil diubah!', 'data' => $topik]);
    }

    public function destroy($id)
    {
        $topik = Topik::findOrFail($id);
        $topik->delete();
        return response()->json(['status' => 'success', 'message' => 'Topik berhasil dihapus beserta seluruh soal di dalamnya!']);
    }
}
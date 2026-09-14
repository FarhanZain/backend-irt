<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi;
use App\Models\PaketSoal;
use Illuminate\Http\Request;

class PaketSoalController extends Controller
{
    public function index(Request $request)
    {
        // Mengambil parameter 'per_page', jika tidak ada default ke 5 data per halaman
        $perPage = $request->get('per_page', 5);
        $paket = PaketSoal::orderBy('id_paket', 'desc')->paginate($perPage);
        
        return response()->json($paket); 
        // Laravel otomatis menyertakan metadata: current_page, last_page, total, dll.
    }

    public function store(Request $request)
    {
        $validasi = $request->validate([
            'nama_paket' => 'required|string|max:255',
            'jadwal_mulai' => 'required|date',
            'jadwal_selesai' => 'required|date|after:jadwal_mulai',
            'tipe_soal' => 'required|string'
        ]);

        $paket = PaketSoal::create($validasi);

        Notifikasi::create([
            'paket_id' => $paket->id_paket, // Mengambil ID dari paket yang baru disimpan
            'nama_paket_snapshot' => $paket->nama_paket,
            'tipe' => 'tambah'
        ]);

        return response()->json(['status' => 'success', 'message' => 'Paket soal berhasil dibuat!', 'data' => $paket], 201);
    }

    public function show($id)
    {
        $paket = PaketSoal::findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $paket]);
    }

    public function update(Request $request, $id)
    {
        $paket = PaketSoal::findOrFail($id);
        $validasi = $request->validate([
            'nama_paket' => 'required|string|max:255',
            'jadwal_mulai' => 'required|date',
            'jadwal_selesai' => 'required|date|after:jadwal_mulai',
            'tipe_soal' => 'required|string'
        ]);

        $paket->update($validasi);

        Notifikasi::create([
            'paket_id' => $paket->id_paket,
            'nama_paket_snapshot' => $paket->nama_paket, // Menyimpan nama versi yang terbaru
            'tipe' => 'update'
        ]);

        return response()->json(['status' => 'success', 'message' => 'Paket soal berhasil diperbarui!', 'data' => $paket]);
    }

    public function destroy($id)
    {
        $paket = PaketSoal::findOrFail($id);

        Notifikasi::create([
            'paket_id' => $paket->id_paket,
            'nama_paket_snapshot' => $paket->nama_paket, // Menyimpan nama versi yang terbaru
            'tipe' => 'hapus'
        ]);

        $paket->delete();
        return response()->json(['status' => 'success', 'message' => 'Paket soal berhasil dihapus!']);
    }
}
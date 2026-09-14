<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    // 1. TAMPILKAN DATA USER
    public function index(Request $request)
    {
        // Mengambil parameter per_page dari frontend, jika kosong default ke 5 data per halaman
        $perPage = $request->get('per_page', 10);
        
        $users = User::orderBy('id_user', 'desc')->paginate($perPage);
        
        // Laravel otomatis mengonversi struktur menjadi metadata objek pagination lengkap
        return response()->json($users, 200);
    }

    // 2. TAMBAH DATA USER
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:255|unique:users,nama',
            'role' => 'required|in:admin,peserta,pengembang soal',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'nama' => $request->nama,
            'role' => $request->role,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User berhasil ditambahkan!',
            'data' => $user
        ], 200);
    }

    // 3. EDIT DATA USER (Hanya Nama & Role)
    public function update(Request $request, $id_user)
    {
        $user = User::find($id_user);
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan!'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:255|unique:users,nama,' . $id_user . ',id_user',
            'role' => 'required|in:admin,peserta,pengembang soal',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Hanya mengizinkan update nama dan role saja
        $user->update([
            'nama' => $request->nama,
            'role' => $request->role
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User berhasil diperbarui!',
            'data' => $user
        ], 200);
    }

    // 4. HAPUS DATA USER
    public function destroy($id_user)
    {
        $user = User::find($id_user);
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan!'], 404);
        }

        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'User berhasil dihapus!'
        ], 200);
    }
}
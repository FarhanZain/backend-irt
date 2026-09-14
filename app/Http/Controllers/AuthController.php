<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str; // <-- Tambah ini untuk generate string acak

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:255|unique:users,nama',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user = User::create([
            'nama' => $request->nama,
            'role' => 'peserta',
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Register berhasil!',
            'user' => $user
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'nama' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('nama', $request->nama)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Nama atau password salah!'
            ], 401);
        }

        // Kunci alternatif: Generate token manual tanpa library/trait apa pun
        $plainToken = Str::random(60); 
        $customToken = base64_encode($user->id_user . '|' . $plainToken);

        return response()->json([
            'message' => 'Login berhasil!',
            'access_token' => $customToken, // Token manual siap pakai
            'token_type' => 'Bearer',
            'user' => [
                'id_user' => $user->id_user,
                'nama' => $user->nama,
                'role' => $user->role
            ]
        ]);
    }
}
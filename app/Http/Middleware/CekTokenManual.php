<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CekTokenManual
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Ambil token dari header 'Authorization: Bearer <token>'
        $authHeader = $request->header('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['message' => 'Token tidak ditemukan, akses ditolak!'], 401);
        }

        $token = str_replace('Bearer ', '', $authHeader);

        try {
            // 2. Decode token base64 (Format: id_user|string_acak)
            $decoded = base64_decode($token);
            $userId = explode('|', $decoded)[0];

            // 3. Validasi user ke database
            $user = User::find($userId);

            if (!$user) {
                return response()->json(['message' => 'User tidak valid!'], 401);
            }

            // Simpan data user ke dalam request agar bisa diakses di Controller jika butuh
            $request->merge(['auth_user' => $user]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Token tidak valid!'], 401);
        }

        return $next($request);
    }
}
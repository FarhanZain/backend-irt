<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\IrtPengembangController;
use App\Http\Controllers\IrtPesertaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaketSoalController;
use App\Http\Controllers\PengembangPaketSoalController;
use App\Http\Controllers\SoalController;
use App\Http\Controllers\TopikController;
use App\Http\Controllers\UjianPesertaController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\CekTokenManual;
use Illuminate\Support\Facades\Route;

// Route Terbuka (Bisa diakses siapa saja)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/stream-notif', [NotificationController::class, 'checkNotifications']);

// Route Terproteksi (Wajib membawa Token valid di Header-nya)
Route::middleware([CekTokenManual::class])->group(function () {
    
    // API CRUD User
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id_user}', [UserController::class, 'update']);
    Route::delete('/users/{id_user}', [UserController::class, 'destroy']);

    // API Paket Soal
    Route::apiResource('paket-soal', PaketSoalController::class);

    // API Topik (Berdasarkan paket_id)
    Route::get('topik/paket/{paket_id}', [TopikController::class, 'getTopikByPaket']);
    Route::apiResource('topik', TopikController::class)->except(['index']);

    // API Soal & Jawaban
    Route::get('soal/topik/{topik_id}', [SoalController::class, 'getSoalByTopik']);
    Route::post('soal', [SoalController::class, 'store']);
    Route::delete('soal/{id_soal}', [SoalController::class, 'destroy']);
    Route::put('soal/{id_soal}', [SoalController::class, 'update']);

    Route::get('/pengembang/paket-soal', [PengembangPaketSoalController::class, 'index']);
    Route::get('/pengembang/paket-soal/{id}', [PengembangPaketSoalController::class, 'show']);

    Route::get('/pengembang/dashboard-irt/{id_paket}', [IrtPengembangController::class, 'showDashboardHasil']);

    // ENGINE RUNNING EXAM PESERTA
    Route::prefix('peserta')->group(function () {
        Route::get('/paket', [UjianPesertaController::class, 'index']);
        Route::get('/paket/{id}', [UjianPesertaController::class, 'showDetailPaket']);
        
        // Method POST untuk inisialisasi & penarikan soal awal
        Route::post('/ujian/mulai', [UjianPesertaController::class, 'mulaiUjian']);
        
        // Method POST Baru untuk submit borongan lembar jawaban di akhir
        Route::post('/ujian/submit-final', [UjianPesertaController::class, 'submitSemuaJawaban']);

        // Endpoint Baru: Mengambil hasil olahan IRT untuk Dashboard Peserta
        Route::get('/ujian/hasil-irt/{paket_id}', [IrtPesertaController::class, 'getDashboardIrt']);
    });
    
    // Contoh endpoint data dashboard
    Route::get('/dashboard-data', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'Selamat datang di data aman API!'
        ]);
    });

});
<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function checkNotifications()
    {
        try {
            // Ambil data log langsung urut berdasarkan ID terbesar (paling baru)
            $logs = Notifikasi::orderBy('id', 'desc')->limit(10)->get();
            $notifications = [];

            foreach ($logs as $log) {
                switch ($log->tipe) {
                    case 'tambah':
                        $message = '✅ Paket Soal "' . $log->nama_paket_snapshot . '" baru ditambahkan.';
                        break;
                    case 'update':
                        $message = '✏️ Paket Soal "' . $log->nama_paket_snapshot . '" telah diperbarui.';
                        break;
                    case 'hapus':
                        $message = '❌ Paket Soal "' . $log->nama_paket_snapshot . '" telah dihapus.';
                        break;
                    case 'irt':
                        $message = '📊 Paket Soal "' . $log->nama_paket_snapshot . '" sudah dilakukan analisis IRT.';
                        break;
                    default:
                        $message = '🔔 Ada aktivitas pada paket "' . $log->nama_paket_snapshot . '".';
                        break;
                }

                $notifications[] = [
                    'id' => 'log-' . $log->id, // Menggunakan ID log agar key unik terjamin di Next.js
                    'message' => $message
                ];
            }

            return response()->json([
                'status' => 'success',
                'notifications' => $notifications
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
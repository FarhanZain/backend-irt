<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ujian_peserta', function (Blueprint $table) {

            $table->id('id_ujian');

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('paket_id');

            $table->datetime('waktu_mulai')->nullable();
            $table->datetime('waktu_selesai')->nullable();

            $table->foreign('user_id')
                ->references('id_user')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('paket_id')
                ->references('id_paket')
                ->on('paket_soal')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ujian_peserta');
    }
};
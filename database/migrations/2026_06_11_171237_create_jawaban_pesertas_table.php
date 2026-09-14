<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jawaban_peserta', function (Blueprint $table) {

            $table->id('id_jawaban_peserta');

            $table->unsignedBigInteger('ujian_id');
            $table->unsignedBigInteger('soal_id');
            $table->unsignedBigInteger('jawaban_soal_id');

            $table->boolean('is_benar');

            $table->foreign('ujian_id')
                ->references('id_ujian')
                ->on('ujian_peserta')
                ->cascadeOnDelete();

            $table->foreign('soal_id')
                ->references('id_soal')
                ->on('soal')
                ->cascadeOnDelete();

            $table->foreign('jawaban_soal_id')
                ->references('id_jawaban_soal')
                ->on('jawaban_soal')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jawaban_peserta');
    }
};
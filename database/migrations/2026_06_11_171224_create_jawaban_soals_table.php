<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jawaban_soal', function (Blueprint $table) {

            $table->id('id_jawaban_soal');

            $table->unsignedBigInteger('soal_id');

            $table->text('teks_jawaban');

            $table->boolean('is_kunci_jawaban')
                ->default(false);

            $table->foreign('soal_id')
                ->references('id_soal')
                ->on('soal')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jawaban_soal');
    }
};
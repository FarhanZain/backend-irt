<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('irt_soal', function (Blueprint $table) {

            $table->id('id_irt_soal');

            $table->unsignedBigInteger('paket_id');
            $table->unsignedBigInteger('soal_id');

            $table->decimal('tingkat_kesulitan',8,4)->nullable();
            $table->string('kategori_kesulitan')->nullable();

            $table->decimal('daya_pembeda',8,4)->nullable();
            $table->string('kategori_daya_pembeda')->nullable();

            $table->decimal('tebakan',8,4)->nullable();

            $table->string('model_irt')->nullable();

            $table->foreign('paket_id')
                ->references('id_paket')
                ->on('paket_soal')
                ->cascadeOnDelete();

            $table->foreign('soal_id')
                ->references('id_soal')
                ->on('soal')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('irt_soal');
    }
};
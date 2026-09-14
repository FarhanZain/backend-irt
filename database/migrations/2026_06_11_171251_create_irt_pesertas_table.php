<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('irt_peserta', function (Blueprint $table) {

            $table->id('id_irt_peserta');

            $table->unsignedBigInteger('paket_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('topik_id');

            $table->decimal('theta',8,4)->nullable();
            $table->integer('skor')->nullable();
            $table->decimal('akurasi',5,2)->nullable();

            $table->string('status_kemampuan')->nullable();

            $table->foreign('paket_id')
                ->references('id_paket')
                ->on('paket_soal')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id_user')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('topik_id')
                ->references('id_topik')
                ->on('topik')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('irt_peserta');
    }
};
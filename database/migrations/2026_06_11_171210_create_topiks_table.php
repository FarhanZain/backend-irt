<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('topik', function (Blueprint $table) {
            $table->id('id_topik');

            $table->unsignedBigInteger('paket_id');

            $table->string('nama_topik');

            $table->foreign('paket_id')
                ->references('id_paket')
                ->on('paket_soal')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topik');
    }
};
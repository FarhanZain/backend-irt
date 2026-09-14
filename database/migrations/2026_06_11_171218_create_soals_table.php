<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('soal', function (Blueprint $table) {

            $table->id('id_soal');

            $table->unsignedBigInteger('topik_id');

            $table->longText('pertanyaan');

            $table->foreign('topik_id')
                ->references('id_topik')
                ->on('topik')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soal');
    }
};
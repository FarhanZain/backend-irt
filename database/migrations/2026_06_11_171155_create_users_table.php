<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id('id_user');
            $table->string('nama');
            $table->enum('role', ['admin', 'peserta', 'pengembang soal']);
            $table->string('password');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
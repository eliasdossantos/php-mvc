<?php

use Core\Migration;
use Core\Schema;
use Core\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('CASCADE');
            $table->string('token_hash', 64); // SHA-256 hex — nunca o token em texto puro
            $table->string('nome', 100)->default('api');
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->unique('token_hash');
            $table->index('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};

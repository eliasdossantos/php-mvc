<?php

use Framework\Migration;
use Framework\Schema;
use Framework\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('redefinicoes_senha', function (Blueprint $table) {
            $table->id();

            // Usuário que solicitou a redefinição
            $table->string('email', 180);

            // Token utilizado no link de redefinição
            $table->string('token', 100);

            // Data e hora limite para utilização do token
            $table->dateTime('expira_em');

            // Controle de utilização do token
            $table->boolean('usado')->default(0);

            // Auditoria
            $table->dateTime('created_at')
                ->default('CURRENT_TIMESTAMP', raw: true);

            // Índices
            $table->index('email');
            $table->unique('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redefinicoes_senha');
    }
};

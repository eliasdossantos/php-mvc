<?php

use Core\Migration;
use Core\Schema;
use Core\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            // Identificação
            $table->id();
            $table->string('nome', 150);

            // Autenticação
            $table->string('email', 180);
            $table->string('senha', 255);

            // Controle de acesso
            $table->string('perfil', 40)->default('member');
            $table->boolean('ativo')->default(1);

            // Verificação e acesso
            $table->dateTime('email_verificado_em')->nullable();
            $table->dateTime('ultimo_login_em')->nullable();
            $table->string('lembrar_token', 100)->nullable();

            // Informações complementares
            $table->string('telefone', 20)->nullable();
            $table->string('avatar', 255)->nullable();
            $table->json('preferencias')->nullable();

            // Auditoria
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->unique('email');
            $table->index('ativo');
            $table->index('perfil');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};

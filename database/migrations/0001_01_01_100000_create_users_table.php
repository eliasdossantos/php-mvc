<?php

use Core\Migration;
use Core\Schema;
use Core\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            // Identidade
            $table->id();
            $table->string('name', 150);

            // Autenticação
            $table->string('email', 180);
            $table->string('password', 255);

            // Controle de acesso
            $table->string('role', 40)->default('member');
            $table->boolean('active')->default(1);

            // Verificação e acesso
            $table->dateTime('email_verified_at')->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->string('remember_token', 100)->nullable();

            // Informações complementares
            $table->string('phone', 20)->nullable();
            $table->string('avatar', 255)->nullable();
            $table->json('preferences')->nullable();

            // Auditoria
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->unique('email', 'uniq_users_email');
            $table->index('active', 'idx_users_active');
            $table->index('role', 'idx_users_role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

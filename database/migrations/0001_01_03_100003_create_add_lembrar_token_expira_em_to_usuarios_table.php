<?php

use Core\Migration;
use Core\Schema;
use Core\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            // Necessário para o "lembrar de mim" (App\Core\Auth) expirar o token corretamente
            $table->dateTime('lembrar_token_expira_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('lembrar_token_expira_em');
        });
    }
};

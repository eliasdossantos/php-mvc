<?php

use Framework\Migration;
use Framework\Schema;
use Framework\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Permite expirar o token de lembrança usado por Framework\Auth.
            $table->dateTime('remember_token_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('remember_token_expires_at');
        });
    }
};

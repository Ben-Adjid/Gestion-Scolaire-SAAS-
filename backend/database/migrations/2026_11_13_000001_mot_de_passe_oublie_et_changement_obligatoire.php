<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            // Mot de passe temporaire : l'utilisateur doit le changer avant d'utiliser l'application.
            $table->boolean('doit_changer_mot_de_passe')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('doit_changer_mot_de_passe'));
        Schema::dropIfExists('password_reset_tokens');
    }
};

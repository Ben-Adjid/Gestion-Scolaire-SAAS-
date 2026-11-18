<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            // Valeurs avant et après la modification (notes, paiements, frais) : historique consultable.
            $table->json('valeurs_avant')->nullable();
            $table->json('valeurs_apres')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('audits', fn (Blueprint $table) => $table->dropColumn(['valeurs_avant', 'valeurs_apres']));
    }
};

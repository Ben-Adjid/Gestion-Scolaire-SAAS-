<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Retrait du paiement en ligne : les frais se règlent en espèces ou par chèque, au guichet.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('transactions_en_ligne');

        Schema::table('etablissements', function (Blueprint $table) {
            if (Schema::hasColumn('etablissements', 'paiement_identifiants')) {
                $table->dropColumn('paiement_identifiants');
            }
            if (Schema::hasColumn('etablissements', 'paiement_prestataire')) {
                $table->dropColumn('paiement_prestataire');
            }
        });
    }

    public function down(): void
    {
        Schema::table('etablissements', function (Blueprint $table) {
            if (! Schema::hasColumn('etablissements', 'paiement_prestataire')) {
                $table->string('paiement_prestataire', 30)->nullable();
                $table->text('paiement_identifiants')->nullable();
            }
        });
    }
};

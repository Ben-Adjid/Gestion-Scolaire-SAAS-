<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Reçu officiel : ville et e-mail de l'établissement, agent de caisse ayant enregistré chaque paiement.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etablissements', function (Blueprint $table) {
            if (! Schema::hasColumn('etablissements', 'ville')) {
                $table->string('ville', 120)->nullable()->after('adresse');
            }
            if (! Schema::hasColumn('etablissements', 'email')) {
                $table->string('email', 255)->nullable()->after('telephone');
            }
        });

        Schema::table('paiements', function (Blueprint $table) {
            if (! Schema::hasColumn('paiements', 'enregistre_par')) {
                $table->foreignId('enregistre_par')->nullable()->after('statut')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            if (Schema::hasColumn('paiements', 'enregistre_par')) {
                $table->dropConstrainedForeignId('enregistre_par');
            }
        });

        Schema::table('etablissements', function (Blueprint $table) {
            $colonnes = array_values(array_filter(['ville', 'email'], fn ($c) => Schema::hasColumn('etablissements', $c)));
            if ($colonnes) {
                $table->dropColumn($colonnes);
            }
        });
    }
};

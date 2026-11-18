<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etablissements', function (Blueprint $table) {
            $table->string('couleur_principale', 7)->nullable();
        });

        // Historique des renouvellements d'abonnement (enregistrés par la plateforme).
        Schema::create('abonnement_paiements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('etablissement_id')->index();
            $table->string('plan');
            $table->unsignedInteger('montant');
            $table->string('mode_paiement');
            $table->string('reference')->nullable();
            $table->date('periode_debut');
            $table->date('periode_fin');
            $table->foreignId('enregistre_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Demandes de support prioritaire, traitées par la plateforme.
        Schema::create('support_demandes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('etablissement_id')->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('sujet');
            $table->text('message');
            $table->string('statut')->default('ouverte');
            $table->text('reponse')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_demandes');
        Schema::dropIfExists('abonnement_paiements');
        Schema::table('etablissements', function (Blueprint $table) {
            $table->dropColumn('couleur_principale');
        });
    }
};

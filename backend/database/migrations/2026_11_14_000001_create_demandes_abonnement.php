<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_abonnement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan');
            $table->unsignedTinyInteger('duree_mois');
            $table->unsignedInteger('montant');
            $table->string('mode_paiement');
            $table->string('reference', 100)->unique();
            $table->string('justificatif_path')->nullable();
            $table->string('statut')->default('en_attente')->index();
            $table->foreignId('traitee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motif_refus')->nullable();
            $table->foreignId('abonnement_paiement_id')->nullable()->constrained('abonnement_paiements')->nullOnDelete();
            $table->timestamps();

            $table->index(['etablissement_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_abonnement');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Paiement en ligne des frais scolaires par les parents (l'argent va sur le compte prestataire de l'école).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etablissements', function (Blueprint $table) {
            if (! Schema::hasColumn('etablissements', 'paiement_prestataire')) {
                $table->string('paiement_prestataire', 30)->nullable();
                // Identifiants du prestataire, chiffrés (cast « encrypted:array »).
                $table->text('paiement_identifiants')->nullable();
            }
        });

        if (! Schema::hasTable('transactions_en_ligne')) {
            Schema::create('transactions_en_ligne', function (Blueprint $table) {
                $table->id();
                $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
                $table->foreignId('frais_scolaire_id')->constrained('frais_scolaires')->cascadeOnDelete();
                $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reference', 40)->unique();
                $table->unsignedBigInteger('montant');
                $table->string('devise', 5);
                $table->string('prestataire', 30);
                $table->string('statut', 20)->default('en_attente');
                $table->text('url_paiement')->nullable();
                $table->json('reponse_prestataire')->nullable();
                $table->foreignId('paiement_id')->nullable()->constrained('paiements')->nullOnDelete();
                $table->timestamps();

                $table->index(['etablissement_id', 'statut']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions_en_ligne');
        Schema::table('etablissements', function (Blueprint $table) {
            if (Schema::hasColumn('etablissements', 'paiement_prestataire')) {
                $table->dropColumn(['paiement_prestataire', 'paiement_identifiants']);
            }
        });
    }
};

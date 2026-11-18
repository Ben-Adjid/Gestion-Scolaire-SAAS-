<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Rendez-vous parents-professeurs : le parent propose des créneaux, l'enseignant en confirme un.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rendez_vous')) {
            return;
        }

        Schema::create('rendez_vous', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('enseignant_id')->constrained('enseignants')->cascadeOnDelete();
            $table->string('motif', 500);
            $table->json('creneaux_proposes');
            $table->dateTime('date_retenue')->nullable();
            $table->string('lieu', 150)->nullable();
            $table->string('statut', 20)->default('demande');
            $table->string('reponse', 500)->nullable();
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['etablissement_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendez_vous');
    }
};

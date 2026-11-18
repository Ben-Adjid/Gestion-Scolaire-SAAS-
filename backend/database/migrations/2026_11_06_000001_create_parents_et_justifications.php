<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_etudiant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
            $table->string('lien')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'etudiant_id']);
        });

        Schema::create('justifications_absence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('absence_id')->constrained('absences')->cascadeOnDelete();
            $table->foreignId('demandeur_id')->constrained('users')->cascadeOnDelete();
            $table->text('motif');
            $table->string('statut')->default('en_attente');
            $table->foreignId('decide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decide_le')->nullable();
            $table->text('commentaire_decision')->nullable();
            $table->unsignedBigInteger('etablissement_id')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('justifications_absence');
        Schema::dropIfExists('parent_etudiant');
    }
};

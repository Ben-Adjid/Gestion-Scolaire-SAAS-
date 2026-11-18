<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Téléphone du compte (utilisé pour les messages WhatsApp aux parents).
        Schema::table('users', function (Blueprint $table) {
            $table->string('telephone', 30)->nullable();
        });

        // Journal des notifications envoyées : un événement n'est notifié qu'une fois par canal et par destinataire.
        Schema::create('notifications_envoyees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('etablissement_id')->index();
            $table->foreignId('destinataire_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->unsignedBigInteger('reference_id');
            $table->string('canal');
            $table->string('statut');
            $table->string('erreur')->nullable();
            $table->timestamps();
            $table->unique(['type', 'reference_id', 'canal', 'destinataire_id'], 'notification_unique');
        });

        // Planning des examens, par classe et matière.
        Schema::create('examens_planning', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('etablissement_id')->index();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->date('date');
            $table->time('heure_debut');
            $table->unsignedSmallInteger('duree_minutes')->default(120);
            $table->string('salle')->nullable();
            $table->timestamps();
            $table->index(['classe_id', 'date']);
        });

        // Fil d'actualité de l'établissement (annonces, sorties, événements).
        Schema::create('actualites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('etablissement_id')->index();
            $table->foreignId('auteur_id')->constrained('users')->cascadeOnDelete();
            $table->string('titre');
            $table->text('contenu');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actualites');
        Schema::dropIfExists('examens_planning');
        Schema::dropIfExists('notifications_envoyees');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('telephone');
        });
    }
};

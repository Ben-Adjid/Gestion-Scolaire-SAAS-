<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Préférences de notification des parents et autorisations de sortie signées.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'preferences_notifications')) {
                $table->json('preferences_notifications')->nullable();
            }
        });

        if (! Schema::hasTable('autorisations_sortie')) {
            Schema::create('autorisations_sortie', function (Blueprint $table) {
                $table->id();
                $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
                $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
                $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
                $table->date('date_sortie');
                $table->time('heure_sortie');
                $table->string('motif', 255);
                $table->string('accompagnant', 150)->nullable();
                // Signature électronique simple : nom tapé, horodatage, adresse IP et empreinte du contenu signé.
                $table->string('signature_nom', 150);
                $table->timestamp('signe_le');
                $table->string('signature_ip', 45)->nullable();
                $table->string('signature_empreinte', 64);
                $table->string('statut', 20)->default('en_attente');
                $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('traite_le')->nullable();
                $table->string('commentaire', 255)->nullable();
                $table->timestamp('sortie_le')->nullable();
                $table->timestamps();

                $table->index(['etablissement_id', 'date_sortie']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('autorisations_sortie');
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'preferences_notifications')) {
                $table->dropColumn('preferences_notifications');
            }
        });
    }
};

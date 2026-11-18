<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Double authentification (TOTP) : secret chiffré, activé une fois confirmé par un code.
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });

        // Calendrier scolaire : rentrée, congés, examens, réunions.
        Schema::create('evenements_calendrier', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('etablissement_id')->index();
            $table->string('titre');
            $table->string('type')->default('autre');
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Archives annuelles scellées : instantané figé + empreinte SHA-256 pour détecter toute altération.
        Schema::create('archives_annee', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('etablissement_id')->index();
            $table->string('annee_scolaire', 20);
            $table->longText('donnees');
            $table->char('empreinte', 64);
            $table->foreignId('archive_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['etablissement_id', 'annee_scolaire']);
        });

        // Groupes scolaires (multi-sites) : plusieurs établissements rattachés à un même groupe.
        Schema::create('groupes_scolaires', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->timestamps();
        });

        Schema::table('etablissements', function (Blueprint $table) {
            $table->foreignId('groupe_scolaire_id')->nullable()->constrained('groupes_scolaires')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('etablissements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('groupe_scolaire_id');
        });
        Schema::dropIfExists('groupes_scolaires');
        Schema::dropIfExists('archives_annee');
        Schema::dropIfExists('evenements_calendrier');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_confirmed_at']);
        });
    }
};

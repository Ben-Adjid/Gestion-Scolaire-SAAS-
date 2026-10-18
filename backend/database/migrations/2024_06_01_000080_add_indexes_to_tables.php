<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->index(['etudiant_id', 'trimestre']);
            $table->index(['matiere_id', 'trimestre']);
        });

        Schema::table('emploi_du_temps', function (Blueprint $table) {
            $table->index('classe_id');
            $table->index('enseignant_id');
        });

        Schema::table('absences', function (Blueprint $table) {
            $table->index('etudiant_id');
        });

        Schema::table('inscriptions', function (Blueprint $table) {
            $table->index('classe_id');
        });
    }

    public function down()
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex('notes_etudiant_id_trimestre_index');
            $table->dropIndex('notes_matiere_id_trimestre_index');
        });

        Schema::table('emploi_du_temps', function (Blueprint $table) {
            $table->dropIndex('emploi_du_temps_classe_id_index');
            $table->dropIndex('emploi_du_temps_enseignant_id_index');
        });

        Schema::table('absences', function (Blueprint $table) {
            $table->dropIndex('absences_etudiant_id_index');
        });

        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropIndex('inscriptions_classe_id_index');
        });
    }
};

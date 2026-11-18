<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Chaque note appartient à une année scolaire : la moyenne d'une réinscription ne mélange plus les années.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            if (! Schema::hasColumn('notes', 'annee_scolaire')) {
                $table->string('annee_scolaire', 20)->nullable()->after('statut');
                $table->index(['etudiant_id', 'annee_scolaire']);
            }
        });

        // Notes existantes : année de la fiche de l'élève.
        foreach (DB::table('etudiants')->select('id', 'annee_scolaire')->whereNotNull('annee_scolaire')->get() as $etudiant) {
            DB::table('notes')->where('etudiant_id', $etudiant->id)->whereNull('annee_scolaire')
                ->update(['annee_scolaire' => $etudiant->annee_scolaire]);
        }
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            if (Schema::hasColumn('notes', 'annee_scolaire')) {
                $table->dropIndex(['etudiant_id', 'annee_scolaire']);
                $table->dropColumn('annee_scolaire');
            }
        });
    }
};

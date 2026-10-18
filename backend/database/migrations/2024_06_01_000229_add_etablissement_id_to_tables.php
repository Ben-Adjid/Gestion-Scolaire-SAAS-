<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up() {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'etablissement_id')) {
            Schema::table('users', fn(Blueprint $t) => $t->unsignedBigInteger('etablissement_id')->nullable()->after('enseignant_id'));
        }
        $tables = ['classes', 'etudiants', 'enseignants', 'matieres', 'regles', 'creneaux', 'messages', 'inscriptions', 'notes', 'absences', 'emploi_du_temps'];
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'etablissement_id')) {
                Schema::table($table, fn(Blueprint $t) => $t->unsignedBigInteger('etablissement_id')->default(1));
            }
        }
        DB::table('users')->whereNull('etablissement_id')->update(['etablissement_id' => 1]);
    }
    public function down() {
        // Ne rien faire pour éviter les problèmes de clés étrangères
    }
};

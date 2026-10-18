<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('dossiers_examen', function (Blueprint $table) {
            $table->string('acte_naissance_path')->nullable()->after('acte_naissance');
            $table->string('photos_identite_path')->nullable()->after('photos_identite');
            $table->string('fiche_renseignement_path')->nullable()->after('fiche_renseignement');
        });
    }

    public function down()
    {
        Schema::table('dossiers_examen', function (Blueprint $table) {
            $table->dropColumn(['acte_naissance_path', 'photos_identite_path', 'fiche_renseignement_path']);
        });
    }
};

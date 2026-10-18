<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('etudiants', function (Blueprint $table) {
            $table->string('matricule')->nullable()->unique()->after('id');
            $table->string('annee_scolaire')->nullable()->after('classe_id');
        });
    }

    public function down()
    {
        Schema::table('etudiants', function (Blueprint $table) {
            $table->dropUnique(['matricule']);
            $table->dropColumn(['matricule', 'annee_scolaire']);
        });
    }
};

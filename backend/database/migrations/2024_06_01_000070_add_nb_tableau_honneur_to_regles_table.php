<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // La colonne nb_tableau_honneur est déjà créée dans la migration 031
        // Cette migration est obsolète
    }

    public function down()
    {
        Schema::table('regles', function (Blueprint $table) {
            $table->dropColumn('nb_tableau_honneur');
        });
    }
};

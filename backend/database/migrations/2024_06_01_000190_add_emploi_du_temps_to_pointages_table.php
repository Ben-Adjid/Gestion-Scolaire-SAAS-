<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('pointages', function (Blueprint $table) {
            // MySQL utilise l'index unique existant pour la clé étrangère.
            // Créer un index simple avant de retirer l'ancien index unique.
            $table->index('enseignant_id', 'pointages_enseignant_id_index');
            $table->dropUnique(['enseignant_id', 'date']);
            $table->foreignId('emploi_du_temps_id')->nullable()->after('enseignant_id')->constrained('emploi_du_temps')->cascadeOnDelete();
            $table->unique(['enseignant_id', 'emploi_du_temps_id', 'date']);
        });
    }

    public function down()
    {
        Schema::table('pointages', function (Blueprint $table) {
            $table->dropUnique(['enseignant_id', 'emploi_du_temps_id', 'date']);
            $table->dropConstrainedForeignId('emploi_du_temps_id');
            $table->unique(['enseignant_id', 'date']);
            $table->dropIndex('pointages_enseignant_id_index');
        });
    }
};

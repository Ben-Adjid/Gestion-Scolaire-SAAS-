<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('dossiers_examen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etudiant_id')->unique()->constrained('etudiants')->cascadeOnDelete();
            $table->string('examen')->default('brevet');
            $table->boolean('acte_naissance')->default(false);
            $table->boolean('photos_identite')->default(false);
            $table->boolean('fiche_renseignement')->default(false);
            $table->boolean('paiement')->default(false);
            $table->string('observations')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('dossiers_examen');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('frais_scolaires')) {
            Schema::create('frais_scolaires', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('etudiant_id');
                $table->string('libelle');
                $table->decimal('montant', 10, 2);
                $table->string('annee_scolaire');
                $table->date('date_echeance');
                $table->string('statut')->default('pending');
                $table->unsignedBigInteger('etablissement_id')->default(1);
                $table->timestamps();

                $table->foreign('etudiant_id')->references('id')->on('etudiants')->onDelete('cascade');
                $table->index(['etudiant_id', 'annee_scolaire']);
                $table->index('statut');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('frais_scolaires');
    }
};

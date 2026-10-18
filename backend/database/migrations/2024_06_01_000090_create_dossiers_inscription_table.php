<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('dossiers_inscription', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etudiant_id')->unique()->constrained('etudiants')->cascadeOnDelete();
            $table->boolean('acte_naissance')->default(false);
            $table->boolean('bulletin')->default(false);
            $table->boolean('photo')->default(false);
            $table->string('observations')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('dossiers_inscription');
    }
};

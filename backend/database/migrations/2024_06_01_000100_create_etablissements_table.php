<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('etablissements', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('devise')->default('Unité — Travail — Progrès');
            $table->string('ministere')->nullable();
            $table->string('adresse')->nullable();
            $table->string('telephone')->nullable();
            $table->string('annee_scolaire')->default('2025-2026');
            $table->string('prefixe_matricule')->default('LIC');
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        if (\DB::getDriverName() === 'mysql') {
            \DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }
        Schema::dropIfExists('etablissements');
        if (\DB::getDriverName() === 'mysql') {
            \DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
};

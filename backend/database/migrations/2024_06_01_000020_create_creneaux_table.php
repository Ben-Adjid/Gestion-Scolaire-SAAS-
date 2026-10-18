<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('creneaux', function (Blueprint $table) {
            $table->id();
            $table->string('heure_debut');
            $table->string('heure_fin');
            $table->unsignedInteger('ordre');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('creneaux');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('enseignant_matiere', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('enseignant_id')->index();
            $table->unsignedBigInteger('matiere_id')->index();
            $table->timestamps();

            $table->foreign('enseignant_id')
                ->references('id')
                ->on('enseignants')
                ->onDelete('cascade');

            $table->foreign('matiere_id')
                ->references('id')
                ->on('matieres')
                ->onDelete('cascade');

            $table->unique(['enseignant_id', 'matiere_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('enseignant_matiere');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('classe_enseignant', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('classe_id')->index();
            $table->unsignedBigInteger('enseignant_id')->index();
            $table->timestamps();

            $table->foreign('classe_id')
                ->references('id')
                ->on('classes')
                ->onDelete('cascade');

            $table->foreign('enseignant_id')
                ->references('id')
                ->on('enseignants')
                ->onDelete('cascade');

            $table->unique(['classe_id', 'enseignant_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('classe_enseignant');
    }
};

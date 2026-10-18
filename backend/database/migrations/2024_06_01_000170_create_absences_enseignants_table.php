<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('absences_enseignants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enseignant_id')->constrained('enseignants')->cascadeOnDelete();
            $table->date('date');
            $table->string('type')->default('absence');
            $table->string('motif')->nullable();
            $table->boolean('justifie')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('absences_enseignants');
    }
};

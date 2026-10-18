<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('conseils_classe', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('classe_id');
            $table->string('trimestre'); // T1, T2, T3
            $table->string('annee_scolaire');
            $table->date('date_conseil');
            $table->longText('pv'); // Procès-verbal complet
            $table->text('participants')->nullable(); // JSON list of users
            $table->string('pdf_url')->nullable();
            $table->unsignedBigInteger('etablissement_id')->default(1);
            $table->timestamps();

            $table->foreign('classe_id')->references('id')->on('classes')->onDelete('cascade');
            $table->foreign('etablissement_id')->references('id')->on('etablissements')->onDelete('cascade');

            $table->index(['classe_id', 'annee_scolaire']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('conseils_classe');
    }
};

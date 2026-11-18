<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cahier de texte numérique : ce qui a été vu en cours et les devoirs à faire, par classe et matière.
        Schema::create('cahiers_texte', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('etablissement_id')->index();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('enseignant_id')->constrained('enseignants')->cascadeOnDelete();
            $table->date('date');
            $table->text('contenu');
            $table->text('devoirs')->nullable();
            $table->timestamps();
            $table->index(['classe_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cahiers_texte');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dépenses de l'établissement : salaires, fournitures, maintenance, transport…
        Schema::create('depenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('etablissement_id')->index();
            $table->string('categorie', 30);
            $table->string('libelle');
            $table->decimal('montant', 12, 2);
            $table->date('date');
            $table->timestamps();
            $table->index(['etablissement_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depenses');
    }
};

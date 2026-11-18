<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Erreurs serveur regroupées par signature (classe + fichier + ligne) pour la surveillance en production.
        Schema::create('erreurs_serveur', function (Blueprint $table) {
            $table->id();
            $table->string('signature', 40)->unique();
            $table->string('classe');
            $table->text('message');
            $table->string('fichier')->nullable();
            $table->unsignedInteger('ligne')->nullable();
            $table->string('methode', 10)->nullable();
            $table->string('url', 500)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('etablissement_id')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('premiere_fois')->nullable();
            $table->timestamp('derniere_fois')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erreurs_serveur');
    }
};

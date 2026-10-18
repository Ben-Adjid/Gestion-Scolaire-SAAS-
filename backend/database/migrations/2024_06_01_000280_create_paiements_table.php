<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        if (!Schema::hasTable('paiements')) {
            Schema::create('paiements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('frais_scolaire_id');
                $table->unsignedBigInteger('etudiant_id');
                $table->decimal('montant', 10, 2);
                $table->date('date_paiement');
                $table->string('mode_paiement');
                $table->string('reference')->nullable();
                $table->string('statut')->default('completed');
                $table->text('notes')->nullable();
                $table->string('recu_url')->nullable();
                $table->unsignedBigInteger('etablissement_id')->default(1);
                $table->timestamps();
                $table->foreign('frais_scolaire_id')->references('id')->on('frais_scolaires')->onDelete('cascade');
                $table->foreign('etudiant_id')->references('id')->on('etudiants')->onDelete('cascade');
                $table->index(['etudiant_id', 'date_paiement']);
                $table->index('statut');
            });
        }
    }
    public function down() { Schema::dropIfExists('paiements'); }
};

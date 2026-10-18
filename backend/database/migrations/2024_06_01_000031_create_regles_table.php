<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        if (!Schema::hasTable('regles')) {
            Schema::create('regles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('etablissement_id')->default(1);
                $table->unsignedInteger('seuil_admission')->default(10);
                $table->unsignedInteger('seuil_bien')->default(12);
                $table->unsignedInteger('seuil_tres_bien')->default(14);
                $table->unsignedInteger('seuil_excellence')->default(16);
                $table->unsignedInteger('nb_tableau_honneur')->default(5);
                $table->timestamps();
            });
        }
    }
    public function down() { Schema::dropIfExists('regles'); }
};

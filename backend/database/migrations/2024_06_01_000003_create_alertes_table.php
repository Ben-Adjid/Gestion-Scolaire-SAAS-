<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        if (!Schema::hasTable('alertes')) {
            Schema::create('alertes', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('etudiant_id');
                $t->string('type');
                $t->string('severity')->default('warning');
                $t->text('message');
                $t->boolean('acknowledged')->default(false);
                $t->unsignedBigInteger('etablissement_id')->default(1);
                $t->timestamps();
                $t->foreign('etudiant_id')->references('id')->on('etudiants')->cascadeOnDelete();
            });
        }
    }
    public function down() {
        Schema::dropIfExists('alertes');
    }
};

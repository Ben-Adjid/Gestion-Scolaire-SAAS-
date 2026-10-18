<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('matieres')) {
            Schema::create('matieres', function (Blueprint $table) {
                $table->id();
                $table->string('nom');
                $table->string('code')->unique();
                $table->unsignedInteger('coefficient')->default(1);
                $table->string('couleur')->default('#4f46e5');
                $table->unsignedBigInteger('etablissement_id')->default(1);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('matieres');
    }
};

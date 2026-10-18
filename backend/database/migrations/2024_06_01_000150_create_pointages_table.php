<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('enseignants', function (Blueprint $table) {
            $table->string('code_pointe')->nullable()->unique()->after('specialite');
        });

        Schema::table('etablissements', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->integer('rayon_pointage')->default(150);
        });

        Schema::create('pointages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enseignant_id')->constrained('enseignants')->cascadeOnDelete();
            $table->date('date');
            $table->time('heure');
            $table->string('statut')->default('a_l_heure');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->integer('distance_metres')->nullable();
            $table->timestamps();

            $table->unique(['enseignant_id', 'date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('pointages');

        Schema::table('etablissements', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'rayon_pointage']);
        });

        Schema::table('enseignants', function (Blueprint $table) {
            $table->dropColumn('code_pointe');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('convocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
            $table->date('date');
            $table->string('motif')->nullable();
            $table->timestamps();
        });

        Schema::table('appels', function (Blueprint $table) {
            $table->foreignId('confirme_par')->nullable()->after('valide_par')->constrained('users')->nullOnDelete();
            $table->timestamp('confirme_at')->nullable()->after('valide_at');
        });
    }

    public function down()
    {
        Schema::table('appels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirme_par');
            $table->dropColumn('confirme_at');
        });

        Schema::dropIfExists('convocations');
    }
};

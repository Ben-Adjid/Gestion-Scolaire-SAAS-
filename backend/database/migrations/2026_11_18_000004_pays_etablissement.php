<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pays de l'établissement : en-tête des bulletins, monnaie et indicatif téléphonique.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etablissements', function (Blueprint $table) {
            if (! Schema::hasColumn('etablissements', 'pays')) {
                $table->string('pays', 2)->default('TD')->after('nom');
            }
        });
    }

    public function down(): void
    {
        Schema::table('etablissements', function (Blueprint $table) {
            if (Schema::hasColumn('etablissements', 'pays')) {
                $table->dropColumn('pays');
            }
        });
    }
};

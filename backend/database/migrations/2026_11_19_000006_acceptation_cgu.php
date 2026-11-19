<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Preuve de l'acceptation des CGU : version acceptée et date, par compte administrateur.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'cgu_version')) {
                $table->string('cgu_version', 20)->nullable();
                $table->timestamp('cgu_acceptees_le')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'cgu_version')) {
                $table->dropColumn(['cgu_version', 'cgu_acceptees_le']);
            }
        });
    }
};

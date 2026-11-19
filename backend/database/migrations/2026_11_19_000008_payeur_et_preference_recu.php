<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Reçu : nom du payeur saisi à l'encaissement (optionnel) et préférence du parent pour recevoir le reçu par e-mail.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            if (! Schema::hasColumn('paiements', 'payeur_nom')) {
                $table->string('payeur_nom', 120)->nullable()->after('reference');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'recevoir_recu_email')) {
                $table->boolean('recevoir_recu_email')->default(true)->after('telephone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            if (Schema::hasColumn('paiements', 'payeur_nom')) {
                $table->dropColumn('payeur_nom');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'recevoir_recu_email')) {
                $table->dropColumn('recevoir_recu_email');
            }
        });
    }
};

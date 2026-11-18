<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Permissions granulaires (catalogue) et table pivot utilisateur ↔ permission.
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('module');
            $table->string('libelle');
        });

        Schema::create('permission_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'permission_id']);
        });

        $now = now();
        foreach (config('permissions.modules', []) as $module => $codes) {
            foreach ($codes as $code => $libelle) {
                DB::table('permissions')->insert([
                    'code' => $code,
                    'module' => $module,
                    'libelle' => $libelle,
                ]);
            }
        }

        // Relances envoyées (idempotence : une relance par échéance, jour et canal).
        Schema::create('relances_abonnement', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('etablissement_id')->index();
            $table->date('date_fin');
            $table->unsignedTinyInteger('jours_avant');
            $table->string('canal');
            $table->boolean('envoye')->default(false);
            $table->timestamps();
            $table->unique(['etablissement_id', 'date_fin', 'jours_avant', 'canal'], 'relance_unique');
        });

        // Journal des événements de paiement reçus (webhook) : garantit qu'un paiement n'est traité qu'une fois.
        Schema::create('evenements_paiement', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->unsignedBigInteger('etablissement_id')->nullable()->index();
            $table->string('statut');
            $table->json('charge');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evenements_paiement');
        Schema::dropIfExists('relances_abonnement');
        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('permissions');
    }
};

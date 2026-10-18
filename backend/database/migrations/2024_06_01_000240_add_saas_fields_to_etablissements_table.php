<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Ajout des champs SaaS sur la table établissements
        Schema::table('etablissements', function (Blueprint $table) {
            if (!Schema::hasColumn('etablissements', 'plan')) {
                $table->string('plan')->default('standard')->after('responsable')
                    ->comment('starter | standard | premium | entreprise');
            }
            if (!Schema::hasColumn('etablissements', 'statut_abonnement')) {
                $table->string('statut_abonnement')->default('actif')->after('plan')
                    ->comment('actif | suspendu | expire | essai');
            }
            if (!Schema::hasColumn('etablissements', 'abonnement_fin')) {
                $table->date('abonnement_fin')->nullable()->after('statut_abonnement');
            }
        });

        // 2. Ajout du rôle superadmin dans la table users
        // (on étend la validation en PHP, pas de contrainte DB pour les enums)
    }

    public function down()
    {
        Schema::table('etablissements', function (Blueprint $table) {
            $table->dropColumn(['plan', 'statut_abonnement', 'abonnement_fin']);
        });
    }
};

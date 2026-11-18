<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etablissements', function (Blueprint $table) {
            $table->string('bulletin_signature_nom', 120)->nullable();
            $table->string('bulletin_signature_titre', 120)->nullable();
            $table->string('bulletin_mention', 300)->nullable();
            $table->boolean('bulletin_afficher_rang')->default(true);
            $table->string('bulletin_couleur', 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('etablissements', function (Blueprint $table) {
            $table->dropColumn(['bulletin_signature_nom', 'bulletin_signature_titre', 'bulletin_mention', 'bulletin_afficher_rang', 'bulletin_couleur']);
        });
    }
};

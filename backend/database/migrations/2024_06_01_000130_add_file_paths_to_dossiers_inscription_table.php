<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('dossiers_inscription', function (Blueprint $table) {
            $table->string('acte_naissance_path')->nullable()->after('acte_naissance');
            $table->string('bulletin_path')->nullable()->after('bulletin');
        });
    }

    public function down()
    {
        Schema::table('dossiers_inscription', function (Blueprint $table) {
            $table->dropColumn(['acte_naissance_path', 'bulletin_path']);
        });
    }
};

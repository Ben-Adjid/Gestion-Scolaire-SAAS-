<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('etablissements', function (Blueprint $table) {
            $table->string('responsable')->nullable()->after('telephone');
        });
    }

    public function down()
    {
        Schema::table('etablissements', function (Blueprint $table) {
            $table->dropColumn('responsable');
        });
    }
};

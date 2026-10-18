<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->string('type')->default('nouveau')->after('statut');
        });
    }

    public function down()
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};

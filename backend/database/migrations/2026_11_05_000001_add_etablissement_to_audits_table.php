<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->unsignedBigInteger('etablissement_id')->nullable()->after('user_id')->index();
        });

        DB::table('audits')->whereNull('etablissement_id')->update([
            'etablissement_id' => DB::raw('(SELECT etablissement_id FROM users WHERE users.id = audits.user_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropIndex(['etablissement_id']);
            $table->dropColumn('etablissement_id');
        });
    }
};

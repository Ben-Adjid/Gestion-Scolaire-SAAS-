<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Affectation du personnel de direction et de vie scolaire : général, un cycle (collège, lycée) ou un bâtiment.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'affectation')) {
                $table->string('affectation', 100)->nullable()->after('role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'affectation')) {
                $table->dropColumn('affectation');
            }
        });
    }
};

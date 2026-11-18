<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bâtiment des classes (périmètre d'un censeur ou surveillant) et cycle déduit du niveau quand il manque.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            if (! Schema::hasColumn('classes', 'batiment')) {
                $table->string('batiment', 50)->nullable()->after('cycle');
            }
        });

        DB::table('classes')->whereNull('cycle')->whereIn('niveau', ['6ème', '5ème', '4ème', '3ème'])->update(['cycle' => 'college']);
        DB::table('classes')->whereNull('cycle')->update(['cycle' => 'lycee']);
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            if (Schema::hasColumn('classes', 'batiment')) {
                $table->dropColumn('batiment');
            }
        });
    }
};

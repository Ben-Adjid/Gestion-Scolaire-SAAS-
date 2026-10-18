<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        if (Schema::hasTable('classes')) {
            Schema::table('classes', function (Blueprint $t) {
                if (!Schema::hasColumn('classes', 'effectif_max')) {
                    $t->unsignedInteger('effectif_max')->default(35)->after('description');
                }
                if (!Schema::hasColumn('classes', 'cycle')) {
                    $t->string('cycle')->nullable()->after('effectif_max');
                }
            });
        }
    }
    public function down() {
        if (Schema::hasTable('classes')) {
            Schema::table('classes', function (Blueprint $t) {
                if (Schema::hasColumn('classes', 'effectif_max')) {
                    $t->dropColumn('effectif_max');
                }
                if (Schema::hasColumn('classes', 'cycle')) {
                    $t->dropColumn('cycle');
                }
            });
        }
    }
};

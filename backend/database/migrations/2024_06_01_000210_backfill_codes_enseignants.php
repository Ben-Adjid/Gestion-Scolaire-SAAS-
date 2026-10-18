<?php

use App\Models\Enseignant;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        Enseignant::whereNull('code_pointe')->get()->each(function (Enseignant $enseignant) {
            $enseignant->code_pointe = Enseignant::generateCode();
            $enseignant->save();
        });
    }

    public function down()
    {
        //
    }
};

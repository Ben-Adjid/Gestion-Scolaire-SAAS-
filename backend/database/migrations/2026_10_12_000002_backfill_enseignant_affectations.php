<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $maintenant = now();

        $classes = DB::table('emploi_du_temps')->whereNotNull('enseignant_id')
            ->select('classe_id', 'enseignant_id')->distinct()->get();
        foreach ($classes as $ligne) {
            DB::table('classe_enseignant')->insertOrIgnore([
                'classe_id' => $ligne->classe_id,
                'enseignant_id' => $ligne->enseignant_id,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ]);
        }

        $matieres = DB::table('emploi_du_temps')->whereNotNull('enseignant_id')
            ->select('matiere_id', 'enseignant_id')->distinct()->get();
        foreach ($matieres as $ligne) {
            DB::table('enseignant_matiere')->insertOrIgnore([
                'matiere_id' => $ligne->matiere_id,
                'enseignant_id' => $ligne->enseignant_id,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ]);
        }
    }

    public function down(): void
    {
        // Les affectations existantes sont conservées : elles peuvent avoir été modifiées manuellement.
    }
};

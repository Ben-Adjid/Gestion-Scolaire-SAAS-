<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\Etudiant;
use App\Models\Absence;
use Illuminate\Support\Facades\DB;

class ReportingService
{
    public function classReport(Classe $classe, $trimestre = 1)
    {
        $students = $classe->etudiants()
            ->with(['notes' => fn($q) => $q->where('trimestre', $trimestre)])
            ->get();

        $stats = [
            'total_students' => $students->count(),
            'average_grade' => $students->avg(fn($s) => $s->notes->avg('note')),
            'attendance_rate' => $this->calculateAttendanceRate($classe),
            'students_in_difficulty' => $students->filter(fn($s) => $s->notes->avg('note') < 10)->count(),
        ];

        return [
            'classe' => $classe,
            'trimestre' => $trimestre,
            'stats' => $stats,
            'students' => $students,
        ];
    }

    public function performanceReport($annee_scolaire = null)
    {
        $query = Classe::query();

        if ($annee_scolaire) {
            $query->where('annee_scolaire', $annee_scolaire);
        }

        $classes = $query->with('etudiants')->get();

        return $classes->map(fn($c) => [
            'classe' => $c->nom,
            'total_students' => $c->etudiants->count(),
            'average_performance' => $this->calculateClassAverage($c),
        ]);
    }

    private function calculateAttendanceRate(Classe $classe)
    {
        $students = $classe->etudiants;
        if ($students->isEmpty()) return 0;

        $totalAbsences = Absence::whereIn('etudiant_id', $students->pluck('id'))
            ->where('justifie', false)
            ->count();

        $expectedAttendances = $students->count() * 180; // ~180 days per year

        return (($expectedAttendances - $totalAbsences) / $expectedAttendances) * 100;
    }

    private function calculateClassAverage(Classe $classe)
    {
        $notes = DB::table('notes')
            ->whereIn('etudiant_id', $classe->etudiants->pluck('id'))
            ->avg('note');

        return round($notes, 2) ?? 0;
    }
}

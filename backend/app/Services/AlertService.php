<?php

namespace App\Services;

use App\Models\Note;
use App\Models\Absence;
use App\Models\Etudiant;
use App\Models\Alerte;
use Illuminate\Support\Facades\Log;

class AlertService
{
    /**
     * Détecter les élèves en difficulté basé sur les notes
     */
    public function checkStudentPerformance(Etudiant $student)
    {
        // Récupérer les notes récentes (dernier trimestre)
        $recentNotes = Note::where('etudiant_id', $student->id)
            ->where('trimestre', $this->getCurrentTrimester())
            ->get();

        if ($recentNotes->isEmpty()) {
            return false;
        }

        $average = $recentNotes->avg('note');

        // Alerte si moyenne < 10 (risque redoublement)
        if ($average < 10) {
            $this->createAlert($student, 'performance', 'danger', 
                "Élève en difficulté académique (moyenne: $average/20)");
            return true;
        }

        // Alerte si baisse progressive
        $previousNotes = Note::where('etudiant_id', $student->id)
            ->where('trimestre', $this->getCurrentTrimester() - 1)
            ->get();

        if (!$previousNotes->isEmpty()) {
            $prevAvg = $previousNotes->avg('note');
            $decline = $prevAvg - $average;

            if ($decline > 3) {
                $this->createAlert($student, 'decline', 'warning',
                    "Baisse de performance détectée (baisse de $decline points)");
                return true;
            }
        }

        return false;
    }

    /**
     * Détecter les absences anormales
     */
    public function checkAbsences(Etudiant $student)
    {
        $absences = Absence::where('etudiant_id', $student->id)
            ->where('created_at', '>=', now()->subMonth())
            ->count();

        if ($absences >= 3 && $absences < 5) {
            $this->createAlert($student, 'absence', 'warning',
                "$absences absences en 1 mois");
            return true;
        }

        if ($absences >= 5 && $absences < 7) {
            $this->createAlert($student, 'absence', 'danger',
                "$absences absences en 1 mois - Risque de décrochage");
            return true;
        }

        if ($absences >= 7) {
            $this->createAlert($student, 'dropout', 'critical',
                "$absences absences en 1 mois - Décrochage probable");
            return true;
        }

        return false;
    }

    /**
     * Créer une alerte
     */
    private function createAlert($student, $type, $severity, $message)
    {
        // Éviter les doublons
        $existing = Alerte::where('etudiant_id', $student->id)
            ->where('type', $type)
            ->where('created_at', '>=', now()->subHours(6))
            ->exists();

        if (!$existing) {
            Alerte::create([
                'etudiant_id' => $student->id,
                'type' => $type,
                'severity' => $severity,
                'message' => $message,
                'acknowledged' => false,
                'etablissement_id' => $student->etablissement_id,
            ]);

            Log::info('Alert created', [
                'student_id' => $student->id,
                'type' => $type,
                'message' => $message,
            ]);
        }
    }

    /**
     * Obtenir le trimestre actuel
     */
    private function getCurrentTrimester()
    {
        $month = now()->month;
        if ($month <= 3) return 1;
        if ($month <= 6) return 2;
        return 3;
    }
}

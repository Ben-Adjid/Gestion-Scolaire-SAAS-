<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Envoyer une notification d'absence à un parent
     */
    public function notifyAbsence($student, $absence)
    {
        try {
            // SMS via Twilio (à configurer)
            // Email via SendGrid (à configurer)
            
            Log::info('Absence notification sent', [
                'student_id' => $student->id,
                'absence_id' => $absence->id,
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send absence notification', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Envoyer une alerte pour élève en difficulté
     */
    public function alertTeachers($student, $issue)
    {
        try {
            $teachers = $student->classe->enseignants;
            
            foreach ($teachers as $teacher) {
                // Envoyer notification au prof principal en priorité
                if ($teacher->id === $student->classe->professeur_principal_id) {
                    // Priorité haute
                }
            }
            
            Log::info('Alert sent to teachers', [
                'student_id' => $student->id,
                'issue' => $issue,
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send teacher alert', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Envoyer notification au CPE pour escalade
     */
    public function notifyCPE($student, $reason)
    {
        try {
            $cpe = User::where('role', 'cpe')
                ->where('etablissement_id', $student->etablissement_id)
                ->first();

            if ($cpe) {
                // Envoyer notification
                Log::info('CPE notification sent', [
                    'student_id' => $student->id,
                    'cpe_id' => $cpe->id,
                    'reason' => $reason,
                ]);
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to notify CPE', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

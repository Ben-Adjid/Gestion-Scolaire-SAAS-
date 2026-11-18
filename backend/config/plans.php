<?php

// Catalogue des formules. Le frontend lit ce catalogue via /api/etablissement (clé « formules »).
// prix_fcfa : tarif mensuel en FCFA. max_utilisateurs : null = illimité.
$standard = [
    'eleves', 'classes', 'inscriptions', 'enseignants', 'matieres', 'emploi_du_temps',
    'presences', 'notes', 'bulletins', 'palmares', 'frais_base', 'recus', 'messages',
    'dossiers_inscription', 'calendrier_scolaire', 'relances', 'notifications_parents', 'fiche_suivi',
];

$vip = array_merge($standard, [
    'conseil_classe', 'export_comptable', 'alertes',
    'rgpd', 'journal_export', 'dossiers_examen', 'classes_examen', 'analyses_avancees', 'espace_parent', 'cahier_texte',
    'presence_temps_reel', 'planning_examens', 'fil_actualite', 'permissions_granulaires',
    'bulletin_personnalise',
]);

$entreprise = array_merge($vip, [
    'api', 'personnalisation', 'support_prioritaire', 'utilisateurs_illimites', 'comptabilite_avancee',
    'archivage', 'multi_sites',
]);

return [
    'defaut' => 'standard',
    'plans' => [
        'standard' => ['libelle' => 'Standard', 'prix_fcfa' => 25000, 'max_utilisateurs' => 10, 'fonctionnalites' => $standard],
        'vip' => ['libelle' => 'VIP', 'prix_fcfa' => 50000, 'max_utilisateurs' => 25, 'fonctionnalites' => $vip],
        'entreprise' => ['libelle' => 'Entreprise', 'prix_fcfa' => 100000, 'max_utilisateurs' => null, 'fonctionnalites' => $entreprise],
    ],
];

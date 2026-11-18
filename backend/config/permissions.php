<?php

// Catalogue des permissions accordables à un compte, regroupées par module.
// Chaque code doit être réellement contrôlé côté serveur (policy ou middleware perm_or_role).
return [
    'modules' => [
        'Notes' => [
            'notes.modifier' => 'Peut modifier et supprimer les notes (hors notes validées)',
        ],
        'Élèves' => [
            'eleves.importer' => 'Peut importer des élèves depuis un fichier CSV',
            'donnees.exporter' => 'Peut exporter les données de l’établissement (CSV)',
        ],
        'Finances' => [
            'comptabilite.voir' => 'Peut consulter la synthèse comptable',
        ],
    ],
];

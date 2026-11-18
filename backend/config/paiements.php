<?php

return [
    'devise' => env('PAIEMENT_DEVISE', 'XOF'),
    'mobile_money' => [
        'libelle' => env('PAIEMENT_MOBILE_LIBELLE', 'Mobile Money'),
        'numero' => env('PAIEMENT_MOBILE_NUMERO'),
        'titulaire' => env('PAIEMENT_MOBILE_TITULAIRE'),
    ],
    'virement' => [
        'banque' => env('PAIEMENT_BANQUE_NOM'),
        'titulaire' => env('PAIEMENT_BANQUE_TITULAIRE'),
        'compte' => env('PAIEMENT_BANQUE_COMPTE'),
        'iban' => env('PAIEMENT_BANQUE_IBAN'),
        'bic' => env('PAIEMENT_BANQUE_BIC'),
    ],
];

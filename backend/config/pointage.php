<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mode test du pointage
    |--------------------------------------------------------------------------
    |
    | Quand true, la géolocalisation n'est pas exigée (pratique en local).
    | En production, mettre false pour exiger la présence dans l'établissement.
    |
    */
    'mode_test' => env('POINTAGE_MODE_TEST', true),
];

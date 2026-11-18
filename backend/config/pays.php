<?php

// Pays pris en charge : en-tête officiel des bulletins, devise nationale, monnaie et indicatif téléphonique.
// « couleur » : teinte suggérée pour l'espace de l'école (tirée des couleurs nationales, lisible sur fond clair).
$pays = [
    // Afrique centrale (CEMAC, franc CFA BEAC)
    'TD' => ['nom' => 'Tchad', 'republique' => 'RÉPUBLIQUE DU TCHAD', 'devise' => 'Unité — Travail — Progrès', 'monnaie' => 'FCFA', 'code_monnaie' => 'XAF', 'indicatif' => '+235', 'couleur' => '#002664'],
    'CM' => ['nom' => 'Cameroun', 'republique' => 'RÉPUBLIQUE DU CAMEROUN', 'devise' => 'Paix — Travail — Patrie', 'monnaie' => 'FCFA', 'code_monnaie' => 'XAF', 'indicatif' => '+237', 'couleur' => '#007A5E'],
    'CF' => ['nom' => 'Centrafrique', 'republique' => 'RÉPUBLIQUE CENTRAFRICAINE', 'devise' => 'Unité — Dignité — Travail', 'monnaie' => 'FCFA', 'code_monnaie' => 'XAF', 'indicatif' => '+236', 'couleur' => '#003082'],
    'CG' => ['nom' => 'Congo', 'republique' => 'RÉPUBLIQUE DU CONGO', 'devise' => 'Unité — Travail — Progrès', 'monnaie' => 'FCFA', 'code_monnaie' => 'XAF', 'indicatif' => '+242', 'couleur' => '#009543'],
    'GA' => ['nom' => 'Gabon', 'republique' => 'RÉPUBLIQUE GABONAISE', 'devise' => 'Union — Travail — Justice', 'monnaie' => 'FCFA', 'code_monnaie' => 'XAF', 'indicatif' => '+241', 'couleur' => '#009E60'],
    'GQ' => ['nom' => 'Guinée équatoriale', 'republique' => 'RÉPUBLIQUE DE GUINÉE ÉQUATORIALE', 'devise' => 'Unité — Paix — Justice', 'monnaie' => 'FCFA', 'code_monnaie' => 'XAF', 'indicatif' => '+240', 'couleur' => '#3E9A00'],
    // Afrique de l'Ouest (UEMOA, franc CFA BCEAO)
    'SN' => ['nom' => 'Sénégal', 'republique' => 'RÉPUBLIQUE DU SÉNÉGAL', 'devise' => 'Un Peuple — Un But — Une Foi', 'monnaie' => 'FCFA', 'code_monnaie' => 'XOF', 'indicatif' => '+221', 'couleur' => '#00853F'],
    'CI' => ['nom' => 'Côte d’Ivoire', 'republique' => 'RÉPUBLIQUE DE CÔTE D’IVOIRE', 'devise' => 'Union — Discipline — Travail', 'monnaie' => 'FCFA', 'code_monnaie' => 'XOF', 'indicatif' => '+225', 'couleur' => '#D9700F'],
    'ML' => ['nom' => 'Mali', 'republique' => 'RÉPUBLIQUE DU MALI', 'devise' => 'Un Peuple — Un But — Une Foi', 'monnaie' => 'FCFA', 'code_monnaie' => 'XOF', 'indicatif' => '+223', 'couleur' => '#14B53A'],
    'BF' => ['nom' => 'Burkina Faso', 'republique' => 'BURKINA FASO', 'devise' => 'Unité — Progrès — Justice', 'monnaie' => 'FCFA', 'code_monnaie' => 'XOF', 'indicatif' => '+226', 'couleur' => '#009E49'],
    'NE' => ['nom' => 'Niger', 'republique' => 'RÉPUBLIQUE DU NIGER', 'devise' => 'Fraternité — Travail — Progrès', 'monnaie' => 'FCFA', 'code_monnaie' => 'XOF', 'indicatif' => '+227', 'couleur' => '#E05206'],
    'BJ' => ['nom' => 'Bénin', 'republique' => 'RÉPUBLIQUE DU BÉNIN', 'devise' => 'Fraternité — Justice — Travail', 'monnaie' => 'FCFA', 'code_monnaie' => 'XOF', 'indicatif' => '+229', 'couleur' => '#008751'],
    'TG' => ['nom' => 'Togo', 'republique' => 'RÉPUBLIQUE TOGOLAISE', 'devise' => 'Travail — Liberté — Patrie', 'monnaie' => 'FCFA', 'code_monnaie' => 'XOF', 'indicatif' => '+228', 'couleur' => '#006A4E'],
    // Autres pays francophones
    'GN' => ['nom' => 'Guinée', 'republique' => 'RÉPUBLIQUE DE GUINÉE', 'devise' => 'Travail — Justice — Solidarité', 'monnaie' => 'GNF', 'code_monnaie' => 'GNF', 'indicatif' => '+224', 'couleur' => '#009460'],
    'CD' => ['nom' => 'RD Congo', 'republique' => 'RÉPUBLIQUE DÉMOCRATIQUE DU CONGO', 'devise' => 'Justice — Paix — Travail', 'monnaie' => 'FC', 'code_monnaie' => 'CDF', 'indicatif' => '+243', 'couleur' => '#007FFF'],
    'MR' => ['nom' => 'Mauritanie', 'republique' => 'RÉPUBLIQUE ISLAMIQUE DE MAURITANIE', 'devise' => 'Honneur — Fraternité — Justice', 'monnaie' => 'MRU', 'code_monnaie' => 'MRU', 'indicatif' => '+222', 'couleur' => '#006233'],
    'MA' => ['nom' => 'Maroc', 'republique' => 'ROYAUME DU MAROC', 'devise' => 'Dieu — La Patrie — Le Roi', 'monnaie' => 'DH', 'code_monnaie' => 'MAD', 'indicatif' => '+212', 'couleur' => '#C1272D'],
    'MG' => ['nom' => 'Madagascar', 'republique' => 'REPOBLIKAN’I MADAGASIKARA', 'devise' => 'Fitiavana — Tanindrazana — Fandrosoana', 'monnaie' => 'Ar', 'code_monnaie' => 'MGA', 'indicatif' => '+261', 'couleur' => '#007E3A'],
    'DJ' => ['nom' => 'Djibouti', 'republique' => 'RÉPUBLIQUE DE DJIBOUTI', 'devise' => 'Unité — Égalité — Paix', 'monnaie' => 'FDJ', 'code_monnaie' => 'DJF', 'indicatif' => '+253', 'couleur' => '#2E7DC0'],
    'KM' => ['nom' => 'Comores', 'republique' => 'UNION DES COMORES', 'devise' => 'Unité — Solidarité — Développement', 'monnaie' => 'FC', 'code_monnaie' => 'KMF', 'indicatif' => '+269', 'couleur' => '#3A75C4'],
    'BI' => ['nom' => 'Burundi', 'republique' => 'RÉPUBLIQUE DU BURUNDI', 'devise' => 'Unité — Travail — Progrès', 'monnaie' => 'FBu', 'code_monnaie' => 'BIF', 'indicatif' => '+257', 'couleur' => '#1EB53A'],
    'RW' => ['nom' => 'Rwanda', 'republique' => 'REPUBULIKA Y’U RWANDA', 'devise' => 'Ubumwe — Umurimo — Gukunda Igihugu', 'monnaie' => 'FRw', 'code_monnaie' => 'RWF', 'indicatif' => '+250', 'couleur' => '#00A1DE'],
];

return [
    'defaut' => env('PAYS_PAR_DEFAUT', 'TD'),
    'liste' => $pays,
];

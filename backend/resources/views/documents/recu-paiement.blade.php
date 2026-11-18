<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $numero }} — {{ $ecole['nom'] }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { font-family: "Segoe UI", Roboto, Arial, sans-serif; color: #1f2a28; margin: 0; background: #f4f2ee; }
        .page { max-width: 780px; margin: 24px auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 8px 30px rgba(0,0,0,.08); position: relative; }
        .bandeau { background: {{ $couleur }}; color: #fff; padding: 22px 28px; display: flex; align-items: center; justify-content: space-between; gap: 20px; }
        .ecole { display: flex; align-items: center; gap: 16px; }
        .ecole img { height: 64px; width: 64px; object-fit: contain; background: #fff; border-radius: 8px; padding: 4px; }
        .ecole h1 { font-size: 20px; margin: 0; letter-spacing: .3px; }
        .ecole p { margin: 2px 0 0; font-size: 12px; opacity: .9; }
        .document { text-align: right; }
        .document .type { font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px; opacity: .85; }
        .document .numero { font-size: 22px; font-weight: 700; margin-top: 2px; }
        .document .date { font-size: 12px; opacity: .9; }
        .contenu { padding: 26px 28px; }
        .grille { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 28px; }
        .champ span { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .8px; color: #6b7a76; }
        .champ strong { font-size: 15px; }
        .montant { margin: 24px 0; padding: 18px 22px; border-radius: 10px; background: {{ $teinte }}; border-left: 6px solid {{ $couleur }}; display: flex; justify-content: space-between; align-items: center; }
        .montant span { font-size: 13px; color: #445; text-transform: uppercase; letter-spacing: 1px; }
        .montant strong { font-size: 28px; color: {{ $couleur }}; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        td { padding: 10px 0; border-bottom: 1px solid #ece8e1; }
        td.valeur { text-align: right; font-weight: 600; }
        tr.reste td { border-bottom: none; font-size: 16px; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; margin-top: 36px; }
        .signature { border-top: 1px dashed #b9b2a6; padding-top: 8px; font-size: 12px; color: #6b7a76; min-height: 70px; }
        .tampon { position: absolute; right: 40px; top: 150px; transform: rotate(-12deg); border: 4px solid #16a34a; color: #16a34a; font-size: 28px; font-weight: 800; padding: 6px 18px; border-radius: 8px; letter-spacing: 3px; opacity: .85; }
        .pied { padding: 14px 28px; background: #faf8f4; font-size: 11px; color: #7a857f; display: flex; justify-content: space-between; gap: 12px; }
        .actions { text-align: center; margin: 0 0 24px; }
        .actions button { background: {{ $couleur }}; color: #fff; border: none; padding: 10px 22px; border-radius: 8px; font-size: 14px; cursor: pointer; }
        @media print { body { background: #fff; } .page { box-shadow: none; margin: 0; border-radius: 0; } .actions { display: none; } }
    </style>
</head>
<body>
<div class="page">
    <div class="bandeau">
        <div class="ecole">
            @if ($logo)
                <img src="{{ $logo }}" alt="">
            @endif
            <div>
                <h1>{{ $ecole['nom'] }}</h1>
                @if ($ecole['devise'])<p>{{ $ecole['devise'] }}</p>@endif
                @if ($ecole['coordonnees'])<p>{{ $ecole['coordonnees'] }}</p>@endif
            </div>
        </div>
        <div class="document">
            <div class="type">Reçu de paiement</div>
            <div class="numero">{{ $numero }}</div>
            <div class="date">{{ $datePaiement }}</div>
        </div>
    </div>

    @if ($solde)
        <div class="tampon">SOLDÉ</div>
    @endif

    <div class="contenu">
        <div class="grille">
            <div class="champ"><span>Élève</span><strong>{{ $eleve['nom'] }}</strong></div>
            <div class="champ"><span>Matricule</span><strong>{{ $eleve['matricule'] ?: '—' }}</strong></div>
            <div class="champ"><span>Frais</span><strong>{{ $frais['libelle'] }}</strong></div>
            <div class="champ"><span>Année scolaire</span><strong>{{ $frais['annee'] ?: '—' }}</strong></div>
            <div class="champ"><span>Mode de paiement</span><strong>{{ $mode }}</strong></div>
            <div class="champ"><span>Référence</span><strong>{{ $reference ?: '—' }}</strong></div>
        </div>

        <div class="montant">
            <span>Montant reçu</span>
            <strong>{{ $montant }} FCFA</strong>
        </div>

        <table>
            <tr><td>Total des frais</td><td class="valeur">{{ $frais['total'] }} FCFA</td></tr>
            <tr><td>Déjà payé (ce paiement compris)</td><td class="valeur">{{ $frais['paye'] }} FCFA</td></tr>
            <tr class="reste"><td>Reste à payer</td><td class="valeur">{{ $frais['reste'] }} FCFA</td></tr>
        </table>

        <div class="signatures">
            <div class="signature">Signature du caissier</div>
            <div class="signature">Cachet de l’établissement</div>
        </div>
    </div>

    <div class="pied">
        <span>Conservez ce reçu : il fait foi du paiement.</span>
        <span>Émis le {{ $emisLe }}</span>
    </div>
</div>
<div class="actions"><button onclick="window.print()">Imprimer ou enregistrer en PDF</button></div>
</body>
</html>

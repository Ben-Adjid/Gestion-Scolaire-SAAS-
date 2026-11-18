<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $titre }} {{ $numero }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { font-family: "Segoe UI", Roboto, Arial, sans-serif; color: #1f2a28; margin: 0; background: #f4f2ee; }
        .page { max-width: 780px; margin: 24px auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 8px 30px rgba(0,0,0,.08); position: relative; }
        .bandeau { background: {{ $couleur }}; color: #fff; padding: 22px 28px; display: flex; justify-content: space-between; align-items: center; }
        .bandeau h1 { margin: 0; font-size: 20px; }
        .bandeau p { margin: 2px 0 0; font-size: 12px; opacity: .9; }
        .document { text-align: right; }
        .document .type { font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px; opacity: .85; }
        .document .numero { font-size: 22px; font-weight: 700; }
        .contenu { padding: 26px 28px; }
        .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 22px; }
        .partie span { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .8px; color: #6b7a76; margin-bottom: 4px; }
        .partie strong { font-size: 15px; }
        .partie p { margin: 2px 0; font-size: 13px; color: #445; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .8px; color: #fff; background: {{ $couleur }}; padding: 10px 12px; }
        td { padding: 12px; border-bottom: 1px solid #ece8e1; }
        td.droite, th.droite { text-align: right; }
        .total { margin-top: 18px; display: flex; justify-content: flex-end; }
        .total div { background: {{ $teinte }}; border-left: 6px solid {{ $couleur }}; padding: 14px 20px; border-radius: 8px; min-width: 280px; display: flex; justify-content: space-between; gap: 20px; }
        .total span { font-size: 13px; text-transform: uppercase; letter-spacing: 1px; }
        .total strong { font-size: 22px; color: {{ $couleur }}; }
        .statut { display: inline-block; margin-top: 18px; padding: 6px 14px; border-radius: 999px; background: #dcfce7; color: #166534; font-weight: 700; font-size: 12px; letter-spacing: 1px; }
        .pied { padding: 14px 28px; background: #faf8f4; font-size: 11px; color: #7a857f; display: flex; justify-content: space-between; }
        .actions { text-align: center; margin: 0 0 24px; }
        .actions button { background: {{ $couleur }}; color: #fff; border: none; padding: 10px 22px; border-radius: 8px; font-size: 14px; cursor: pointer; }
        @media print { body { background: #fff; } .page { box-shadow: none; margin: 0; border-radius: 0; } .actions { display: none; } }
    </style>
</head>
<body>
<div class="page">
    <div class="bandeau">
        <div>
            <h1>{{ $plateforme }}</h1>
            <p>Abonnement au logiciel de gestion scolaire</p>
        </div>
        <div class="document">
            <div class="type">{{ $titre }}</div>
            <div class="numero">{{ $numero }}</div>
            <p>{{ $dateEmission }}</p>
        </div>
    </div>

    <div class="contenu">
        <div class="parties">
            <div class="partie">
                <span>Établissement</span>
                <strong>{{ $ecole['nom'] }}</strong>
                @if ($ecole['coordonnees'])<p>{{ $ecole['coordonnees'] }}</p>@endif
            </div>
            <div class="partie">
                <span>Règlement</span>
                <strong>{{ $mode }}</strong>
                <p>Référence : {{ $reference ?: '—' }}</p>
            </div>
        </div>

        <table>
            <thead>
                <tr><th>Désignation</th><th>Période</th><th class="droite">Montant</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Abonnement formule {{ $formule }}</td>
                    <td>{{ $periode }}</td>
                    <td class="droite">{{ $montant }} FCFA</td>
                </tr>
            </tbody>
        </table>

        <div class="total"><div><span>Total</span><strong>{{ $montant }} FCFA</strong></div></div>
        <div class="statut">PAYÉ</div>
    </div>

    <div class="pied">
        <span>Merci pour votre confiance.</span>
        <span>{{ $plateforme }}</span>
    </div>
</div>
<div class="actions"><button onclick="window.print()">Imprimer ou enregistrer en PDF</button></div>
</body>
</html>

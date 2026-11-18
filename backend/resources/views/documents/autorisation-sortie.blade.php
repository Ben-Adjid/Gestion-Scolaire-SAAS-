<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Autorisation de sortie {{ $numero }} — {{ $ecole['nom'] }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { font-family: "Segoe UI", Roboto, Arial, sans-serif; color: #1f2a28; margin: 0; background: #f4f2ee; }
        .page { max-width: 780px; margin: 24px auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 8px 30px rgba(0,0,0,.08); }
        .bandeau { background: {{ $couleur }}; color: #fff; padding: 22px 28px; display: flex; align-items: center; justify-content: space-between; gap: 20px; }
        .ecole { display: flex; align-items: center; gap: 16px; }
        .ecole img { height: 64px; width: 64px; object-fit: contain; background: #fff; border-radius: 8px; padding: 4px; }
        .ecole h1 { font-size: 20px; margin: 0; }
        .ecole p { margin: 2px 0 0; font-size: 12px; opacity: .9; }
        .document { text-align: right; }
        .document .type { font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px; opacity: .85; }
        .document .numero { font-size: 22px; font-weight: 700; margin-top: 2px; }
        .contenu { padding: 26px 28px; }
        .statut { display: inline-block; padding: 4px 12px; border-radius: 999px; background: {{ $teinte }}; color: {{ $couleur }}; font-weight: 700; font-size: 13px; }
        .grille { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 28px; margin-top: 18px; }
        .champ span { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .8px; color: #6b7a76; }
        .champ strong { font-size: 15px; }
        .texte { margin: 24px 0; padding: 18px 22px; border-radius: 10px; background: {{ $teinte }}; border-left: 6px solid {{ $couleur }}; line-height: 1.6; }
        .signature { margin-top: 24px; border: 1px solid #e6e1d8; border-radius: 10px; padding: 16px 20px; }
        .signature .nom { font-family: "Brush Script MT", "Segoe Script", cursive; font-size: 30px; color: {{ $couleur }}; }
        .preuve { font-size: 11px; color: #6b7a76; margin-top: 8px; word-break: break-all; }
        .alerte { color: #b91c1c; font-weight: 700; }
        .visa { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; margin-top: 30px; }
        .visa div { border-top: 1px dashed #b9b2a6; padding-top: 8px; font-size: 12px; color: #6b7a76; min-height: 60px; }
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
            <div class="type">Autorisation de sortie</div>
            <div class="numero">{{ $numero }}</div>
        </div>
    </div>

    <div class="contenu">
        <span class="statut">{{ $statut }}</span>

        <div class="grille">
            <div class="champ"><span>Élève</span><strong>{{ $a->etudiant?->prenom }} {{ $a->etudiant?->nom }}</strong></div>
            <div class="champ"><span>Classe</span><strong>{{ $a->etudiant?->classe?->nom ?? '—' }}</strong></div>
            <div class="champ"><span>Date de sortie</span><strong>{{ $a->date_sortie->format('d/m/Y') }}</strong></div>
            <div class="champ"><span>Heure prévue</span><strong>{{ substr((string) $a->heure_sortie, 0, 5) }}</strong></div>
            <div class="champ"><span>Récupéré par</span><strong>{{ $a->accompagnant ?: 'Sort seul' }}</strong></div>
            <div class="champ"><span>Sortie constatée</span><strong>{{ $a->sortie_le ? $a->sortie_le->format('d/m/Y à H:i') : '—' }}</strong></div>
        </div>

        <div class="texte">
            Je soussigné(e) <strong>{{ $a->signature_nom }}</strong>, parent ou responsable légal de
            <strong>{{ $a->etudiant?->prenom }} {{ $a->etudiant?->nom }}</strong>, l’autorise à quitter l’établissement
            le {{ $a->date_sortie->format('d/m/Y') }} à partir de {{ substr((string) $a->heure_sortie, 0, 5) }}.
            <br>Motif : {{ $a->motif }}
            @if ($a->commentaire)
                <br>Observation de l’établissement : {{ $a->commentaire }}
            @endif
        </div>

        <div class="signature">
            <div class="nom">{{ $a->signature_nom }}</div>
            <div class="preuve">
                Signé électroniquement le {{ $a->signe_le->format('d/m/Y à H:i:s') }} depuis le compte parent
                « {{ $a->parent?->name }} ».<br>
                Empreinte SHA-256 : {{ $a->signature_empreinte }}
                @unless ($intacte)
                    <br><span class="alerte">Attention : le contenu ne correspond plus à la signature.</span>
                @endunless
            </div>
        </div>

        <div class="visa">
            <div>Décision : {{ $a->traitePar?->name ?? '—' }} @if ($a->traite_le) le {{ $a->traite_le->format('d/m/Y à H:i') }} @endif</div>
            <div>Cachet de l’établissement</div>
        </div>
    </div>

    <div class="pied">
        <span>{{ $ecole['nom'] }}</span>
        <span>Édité le {{ $emisLe }}</span>
    </div>
</div>
<div class="actions"><button type="button" onclick="window.print()">Imprimer</button></div>
</body>
</html>

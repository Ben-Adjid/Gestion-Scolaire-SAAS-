<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $r['numero'] }}</title>
    <style>
        @page { margin: 14mm 16mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1f2a28; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }
        .entete td { color: #ffffff; }
        .petit { font-size: 8px; text-transform: uppercase; color: #6b7280; letter-spacing: .5px; }
        .gras { font-weight: bold; }
        .droite { text-align: right; }
        .titre { text-align: center; color: {{ $r['ecole']['couleur'] }}; font-size: 13px; font-weight: bold; margin: 18px 0 4px; }
        .tableau th { background: {{ $r['ecole']['couleur'] }}; color: #ffffff; padding: 6px 8px; font-size: 9px; text-align: left; }
        .tableau td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        .encadre { background: {{ $r['ecole']['teinte'] }}; border: 1.5px solid {{ $r['ecole']['couleur'] }}; }
        .badge { color: #ffffff; padding: 5px 12px; font-weight: bold; font-size: 10px; }
        .cachet { border: 1px dashed #b9b2a6; height: 90px; padding: 8px; font-size: 9px; color: #6b7280; }
        .pied { border-top: 1px solid #e5e7eb; margin-top: 16px; padding-top: 8px; font-size: 8px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>

<table class="entete" style="background: {{ $r['ecole']['couleur'] }};">
    <tr>
        <td style="padding: 14px 16px; width: 70px;">
            @if ($r['ecole']['logo'])
                <img src="{{ $r['ecole']['logo'] }}" style="width: 56px; height: 56px; background: #ffffff; padding: 2px;" alt="">
            @endif
        </td>
        <td style="padding: 14px 8px;">
            <div style="font-size: 16px; font-weight: bold;">{{ $r['ecole']['nom'] }}</div>
            <div style="font-size: 9px; margin-top: 4px;">{{ collect([$r['ecole']['adresse'], $r['ecole']['ville'], $r['ecole']['pays']])->filter()->implode(' — ') }}</div>
            <div style="font-size: 9px; margin-top: 2px;">{{ collect([$r['ecole']['telephone'] ? 'Tél : '.$r['ecole']['telephone'] : null, $r['ecole']['email']])->filter()->implode(' · ') }}</div>
        </td>
        <td class="droite" style="padding: 14px 16px; width: 130px;">
            <div class="petit" style="color: #e5e7eb;">Année scolaire</div>
            <div style="font-size: 13px; font-weight: bold;">{{ $r['ecole']['anneeScolaire'] ?: '—' }}</div>
        </td>
    </tr>
</table>

<div class="titre">REÇU DE PAIEMENT / QUITTANCE DE CAISSE</div>

<table style="margin-top: 10px;">
    <tr>
        <td class="gras" style="font-size: 11px;">N° de reçu : {{ $r['numero'] }}</td>
        <td class="droite">Date de l’opération : {{ $r['dateHeure'] }}</td>
    </tr>
</table>

<table style="margin-top: 14px; background: {{ $r['ecole']['teinte'] }};">
    <tr>
        <td style="padding: 10px 12px; width: 40%;">
            <div class="petit">Élève</div>
            <div class="gras" style="font-size: 11px; margin-top: 3px;">{{ $r['eleve']['nom'] }}</div>
            <div class="petit" style="margin-top: 8px;">Matricule</div>
            <div class="gras" style="font-size: 11px; margin-top: 3px;">{{ $r['eleve']['matricule'] }}</div>
        </td>
        <td style="padding: 10px 12px;">
            <div class="petit">Classe et niveau</div>
            <div class="gras" style="font-size: 11px; margin-top: 3px;">{{ $r['eleve']['classe'] }}</div>
        </td>
    </tr>
</table>

<table class="tableau" style="margin-top: 16px;">
    <thead>
        <tr>
            <th style="width: 40%;">Libellé des frais</th>
            <th class="droite">Montant exigible</th>
            <th class="droite">Encaissé ce jour</th>
            <th class="droite">Mode de règlement</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $r['frais']['libelle'] }}</td>
            <td class="droite">{{ $r['frais']['exigible'] }}</td>
            <td class="droite gras">{{ $r['montantEncaisse'] }}</td>
            <td class="droite">{{ $r['mode'] }}</td>
        </tr>
    </tbody>
</table>

<table style="margin-top: 12px;">
    <tr>
        <td style="width: 50%;">
            <div class="petit">Total déjà encaissé</div>
            <div class="gras" style="font-size: 12px; margin-top: 3px;">{{ $r['frais']['totalPaye'] }}</div>
        </td>
        <td>
            <div class="petit">Reste à payer</div>
            <div class="gras" style="font-size: 12px; margin-top: 3px;">{{ $r['frais']['reste'] }}</div>
        </td>
    </tr>
</table>

<table class="encadre" style="margin-top: 16px;">
    <tr>
        <td style="padding: 12px 14px; color: {{ $r['ecole']['couleur'] }}; font-weight: bold; font-size: 10px; letter-spacing: 1px;">MONTANT ENCAISSÉ</td>
        <td class="droite" style="padding: 12px 14px; color: {{ $r['ecole']['couleur'] }}; font-weight: bold; font-size: 16px;">{{ $r['montantEncaisse'] }}</td>
    </tr>
</table>

<div style="margin-top: 14px;">
    @if ($r['frais']['solde'])
        <span class="badge" style="background: #16a34a;">SOLDÉ</span>
    @else
        <span class="badge" style="background: #d97706;">RESTE À PAYER : {{ $r['frais']['reste'] }}</span>
    @endif
</div>

<table style="margin-top: 22px;">
    <tr>
        <td style="width: 55%; font-size: 9px;">
            <div>Encaissé par : {{ $r['agent'] ?: '—' }}</div>
            @if ($r['reference'])
                <div style="margin-top: 4px;">Référence : {{ $r['reference'] }}</div>
            @endif
        </td>
        <td style="width: 45%;">
            <div class="cachet">
                <div>Pour acquit</div>
                <div style="text-align: center; margin-top: 52px;">Cachet &amp; signature de l’établissement</div>
            </div>
        </td>
    </tr>
</table>

<div class="pied">
    Ce reçu est délivré à titre de justificatif de paiement officiel. Aucun duplicata ne sera fourni sans motif valable.
    <br>Édité le {{ now()->format('d/m/Y') }} — {{ $r['ecole']['nom'] }}
</div>

</body>
</html>

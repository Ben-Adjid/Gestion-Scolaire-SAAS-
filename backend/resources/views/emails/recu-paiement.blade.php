<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0; padding:0; background:#f4f2ee; font-family: Arial, Helvetica, sans-serif; color:#1f2a28;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f2ee; padding:24px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px; background:#ffffff; border-radius:10px; overflow:hidden;">
                <tr>
                    <td style="background: {{ $d['ecole']['couleur'] }}; color:#ffffff; padding:18px 24px;">
                        <div style="font-size:18px; font-weight:bold;">{{ $d['ecole']['nom'] }}</div>
                        <div style="font-size:12px; opacity:.9; margin-top:4px;">Reçu de paiement {{ $d['numero'] }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px;">
                        <p style="margin:0 0 14px; font-size:15px;">Bonjour,</p>
                        <p style="margin:0 0 16px; font-size:14px; line-height:1.5;">
                            Nous confirmons l’encaissement suivant pour <strong>{{ $d['eleve']['nom'] }}</strong> ({{ $d['eleve']['classe'] }}).
                        </p>
                        <table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="font-size:14px; background: {{ $d['ecole']['teinte'] }}; border-radius:8px;">
                            <tr><td>Frais</td><td align="right"><strong>{{ $d['frais']['libelle'] }}</strong></td></tr>
                            <tr><td>Montant encaissé</td><td align="right"><strong style="color: {{ $d['ecole']['couleur'] }};">{{ $d['montantEncaisse'] }}</strong></td></tr>
                            <tr><td>Mode de règlement</td><td align="right">{{ $d['mode'] }}</td></tr>
                            <tr><td>Date de l’opération</td><td align="right">{{ $d['dateHeure'] }}</td></tr>
                            <tr><td>Reste à payer</td><td align="right"><strong>{{ $d['frais']['reste'] }}</strong></td></tr>
                        </table>
                        <p style="margin:18px 0 0; font-size:14px; line-height:1.5;">
                            Le reçu officiel est joint à ce message au format PDF. Conservez-le : il fait foi du paiement.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:14px 24px; background:#faf8f4; font-size:11px; color:#6b7280;">
                        Message automatique envoyé par {{ $d['ecole']['nom'] }} via la plateforme de gestion scolaire.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

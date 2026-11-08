<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 16mm 14mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111827; }
        h1 { margin: 0; font-size: 14pt; text-align: center; text-transform: uppercase; letter-spacing: .5pt; }
        .sous-titre { margin-top: 2mm; text-align: center; font-size: 10pt; }
        .projet { margin: 4mm 0; padding: 2mm; border: .3mm solid #b45309; color: #b45309; text-align: center; font-weight: bold; }
        .references { width: 100%; margin: 6mm 0 4mm; border-collapse: collapse; }
        .references td { padding: 1.2mm 0; vertical-align: top; }
        .references td.libelle { width: 38mm; color: #4b5563; }
        table.biens { width: 100%; border-collapse: collapse; }
        table.biens th, table.biens td { border: .2mm solid #9ca3af; padding: 1.4mm 1.6mm; text-align: left; vertical-align: top; }
        table.biens th { background: #f3f4f6; font-size: 8pt; text-transform: uppercase; }
        table.biens td.num { width: 8mm; text-align: center; }
        .total { margin-top: 3mm; font-weight: bold; }
        .observations { margin-top: 5mm; }
        .signatures { width: 100%; margin-top: 12mm; border-collapse: collapse; }
        .signatures td { width: 33%; padding-top: 2mm; text-align: center; vertical-align: top; }
        .signatures .ligne td { height: 24mm; }
    </style>
</head>
<body>
    <h1>Procès-verbal de sortie d’immobilisations</h1>
    <p class="sous-titre">Motif : <strong>{{ $sortie->motif_libelle }}</strong></p>

    @unless($sortie->statut === 'validee')
        <p class="projet">
            {{ $sortie->statut === 'annulee' ? 'DÉCISION ANNULÉE — SANS EFFET' : 'PROJET — décision non encore validée' }}
        </p>
    @endunless

    <table class="references">
        <tr><td class="libelle">N° de décision</td><td><strong>{{ $sortie->numero }}</strong></td></tr>
        <tr><td class="libelle">Date de la décision</td><td>{{ $sortie->date_decision?->format('d/m/Y') ?? '…… / …… / ……………' }}</td></tr>
        @if($sortie->inventaire)
            <tr><td class="libelle">Origine</td><td>Constats de l’inventaire {{ $sortie->inventaire->annee }}</td></tr>
        @endif
        @if($sortie->validee_at)
            <tr><td class="libelle">Validée le</td><td>{{ $sortie->validee_at->format('d/m/Y à H:i') }} par {{ $sortie->validateur?->users ?? '—' }}</td></tr>
        @endif
    </table>

    <table class="biens">
        <thead>
            <tr>
                <th class="num">N°</th>
                <th>Code</th>
                <th>Désignation</th>
                <th>Catégorie</th>
                <th>Site / localisation</th>
                <th>État</th>
                <th>Constat</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sortie->lignes as $ligne)
                <tr>
                    <td class="num">{{ $loop->iteration }}</td>
                    <td>{{ $ligne->code }}</td>
                    <td>{{ $ligne->designation }}</td>
                    <td>{{ $ligne->categorie }}</td>
                    <td>{{ $ligne->site }}@if($ligne->localisation)<br>{{ $ligne->localisation }}@endif</td>
                    <td>{{ $ligne->etat }}</td>
                    <td>{{ $ligne->constat_libelle }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="total">Nombre de biens : {{ $sortie->lignes->count() }}</p>

    @if($sortie->observations)
        <div class="observations"><strong>Observations :</strong><br>{!! nl2br(e($sortie->observations)) !!}</div>
    @endif

    <table class="signatures">
        <tr>
            <td>Établi par</td>
            <td>Vérifié par</td>
            <td>Approuvé par</td>
        </tr>
        <tr class="ligne"><td></td><td></td><td></td></tr>
        <tr>
            <td>Nom, date et signature</td>
            <td>Nom, date et signature</td>
            <td>Nom, date et signature</td>
        </tr>
    </table>
</body>
</html>

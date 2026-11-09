<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: {{ $configuration['page_width'] }}mm {{ $configuration['page_height'] }}mm; margin: 0; }
        * { box-sizing: border-box; }
        html, body {
            width: {{ $configuration['page_width'] }}mm;
            height: {{ $configuration['page_height'] }}mm;
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            color: {{ $configuration['text_color'] }};
        }
        .sheet {
            position: relative;
            width: {{ $configuration['page_width'] }}mm;
            height: {{ $configuration['page_height'] }}mm;
            overflow: hidden;
        }
        .etiquette {
            position: absolute;
            width: {{ $configuration['label_width'] }}mm;
            height: {{ $configuration['label_height'] }}mm;
            overflow: hidden;
            background: {{ $configuration['background_color'] }};
        }
        .contenu { position: absolute; overflow: hidden; }
        .qr {
            position: absolute;
            left: 2.7mm;
            width: {{ $configuration['qr_size'] }}mm;
            height: {{ $configuration['qr_size'] }}mm;
            padding: .4mm;
            background: #fff;
        }
        .qr img { width: 100%; height: 100%; display: block; }
        .infos {
            position: absolute;
            right: 2.7mm;
            left: {{ $configuration['qr_size'] + 5.2 }}mm;
            overflow: hidden;
        }
        .designation { font-size: 7.1pt; font-weight: bold; line-height: 1.08; }
        .composant { margin-top: .7mm; font-size: 5.7pt; line-height: 1.05; opacity: .78; }
        .champ-label { margin-top: 1.4mm; font-size: 4pt; font-weight: bold; text-transform: uppercase; letter-spacing: .35pt; opacity: .48; }
        .site { margin-top: .25mm; font-size: 5.6pt; line-height: 1.05; }
        .code {
            position: absolute;
            right: 2.7mm;
            left: {{ $configuration['qr_size'] + 5.2 }}mm;
            height: 3mm;
            overflow: hidden;
            font-family: DejaVu Sans, sans-serif;
            font-size: 5.2pt;
            font-weight: bold;
            line-height: 1.1;
            letter-spacing: .1pt;
            white-space: nowrap;
            text-align: left;
        }
        @if($configuration['compact_layout'])
        .qr { left: 1.3mm; padding: .25mm; }
        .infos { right: 1.2mm; left: {{ $configuration['qr_size'] + 3 }}mm; }
        .designation { font-size: 5.1pt; line-height: 1.03; }
        .composant { margin-top: .45mm; font-size: 4.2pt; }
        .champ-label { margin-top: .8mm; font-size: 3pt; letter-spacing: .1pt; }
        .site { margin-top: .15mm; font-size: 4.1pt; }
        .code { right: 1.3mm; left: {{ $configuration['qr_size'] + 3 }}mm; font-size: 4.4pt; }
        @endif
        @if($configuration['narrow_layout'])
        .code { left: 1.3mm; text-align: center; }
        @endif
        @if($configuration['short_layout'])
        .qr { left: 1.2mm; padding: .2mm; }
        .designation { font-size: 5pt; line-height: 1.02; }
        .composant { margin-top: .35mm; font-size: 4pt; }
        .champ-label { margin-top: .55mm; font-size: 2.8pt; }
        .site { margin-top: .1mm; font-size: 3.8pt; }
        .code { height: 2.6mm; }
        @endif
    </style>
</head>
<body>
@php
    $casesImpression = collect(array_fill(0, $configuration['start_position'] - 1, null))
        ->concat($etiquettes)
        ->values();
@endphp
@foreach($casesImpression->chunk($configuration['labels_per_page']) as $page)
    <section class="sheet" @if(!$loop->last) style="page-break-after: always;" @endif>
        @foreach($page->values() as $index => $etiquette)
            @php
                $colonne = $index % $configuration['columns'];
                $ligne = intdiv($index, $configuration['columns']);
                $gauche = $configuration['page_margin'] + ($configuration['offset_x'] ?? 0) + ($colonne * ($configuration['label_width'] + $configuration['gap_x']));
                $haut = $configuration['page_margin'] + ($configuration['offset_y'] ?? 0) + ($ligne * ($configuration['label_height'] + $configuration['gap_y']));
                $zone = $configuration['printer_margin'] ?? 0;
                $retraitGauche = max(0, $zone - $gauche);
                $retraitHaut = max(0, $zone - $haut);
                $retraitDroite = max(0, $gauche + $configuration['label_width'] - ($configuration['page_width'] - $zone));
                $retraitBas = max(0, $haut + $configuration['label_height'] - ($configuration['page_height'] - $zone));
                $hauteurContenu = $configuration['label_height'] - $retraitHaut - $retraitBas;
                // Le QR est centré verticalement ; le code reste toujours dans une ligne
                // dédiée au bas de l'étiquette afin de ne jamais recouvrir le site.
                $margeQr = $configuration['narrow_layout']
                    ? 1.2
                    : max(.8, ($hauteurContenu - $configuration['qr_size']) / 2);
                $basCode = 1.1;
                $reserveCode = $configuration['show_code'] ? 4.5 : $margeQr;
            @endphp
            @if($etiquette)
            <article class="etiquette" style="left: {{ $gauche }}mm; top: {{ $haut }}mm;">
                <div class="contenu" style="left: {{ $retraitGauche }}mm; top: {{ $retraitHaut }}mm; width: {{ $configuration['label_width'] - $retraitGauche - $retraitDroite }}mm; height: {{ $hauteurContenu }}mm;">
                    <div class="qr" style="top: {{ $margeQr }}mm;"><img src="data:image/svg+xml;base64,{{ $etiquette['qr_base64'] }}" alt="QR code"></div>
                    <div class="infos" style="top: {{ $margeQr + .3 }}mm; bottom: {{ $reserveCode }}mm;">
                        @if($configuration['show_designation'])
                            <div class="designation">{{ $etiquette['bien']->libelle_article ?? 'Immobilisation' }}</div>
                        @endif
                        @if($etiquette['nom_composant'])
                            <div class="composant">{{ $etiquette['nom_composant'] }}</div>
                        @endif
                        @if($configuration['show_emplacement'])
                            <div class="champ-label">Site</div>
                            <div class="site">{{ $site->site }}</div>
                        @endif
                    </div>
                    @if($configuration['show_code'])
                        {{-- Code d'inventaire en clair (le QR, lui, contient le code de scan GS…). --}}
                        <div class="code" style="bottom: {{ $basCode }}mm;">{{ $etiquette['code_affiche'] ?? $etiquette['code'] }}</div>
                    @endif
                </div>
            </article>
            @endif
        @endforeach
    </section>
@endforeach
</body>
</html>

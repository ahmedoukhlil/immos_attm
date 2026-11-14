@php
    $echelleContenu = ($configuration['content_scale'] ?? 100) / 100;
    $tailleQrRendue = $configuration['qr_size'] * $echelleContenu;
@endphp
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
            left: {{ 2.7 * $echelleContenu }}mm;
            width: {{ $tailleQrRendue }}mm;
            height: {{ $tailleQrRendue }}mm;
            padding: {{ .4 * $echelleContenu }}mm;
            background: #fff;
        }
        .qr img { width: 100%; height: 100%; display: block; }
        .infos {
            position: absolute;
            right: {{ 2.7 * $echelleContenu }}mm;
            left: {{ $tailleQrRendue + (5.2 * $echelleContenu) }}mm;
            overflow: hidden;
        }
        .designation { font-size: {{ 7.1 * $echelleContenu }}pt; font-weight: bold; line-height: 1.08; }
        .composant { margin-top: {{ .7 * $echelleContenu }}mm; font-size: {{ 5.7 * $echelleContenu }}pt; line-height: 1.05; opacity: .78; }
        .champ-label { margin-top: {{ 1.4 * $echelleContenu }}mm; font-size: {{ 4 * $echelleContenu }}pt; font-weight: bold; text-transform: uppercase; letter-spacing: {{ .35 * $echelleContenu }}pt; opacity: .48; }
        .site { margin-top: {{ .25 * $echelleContenu }}mm; font-size: {{ 5.6 * $echelleContenu }}pt; line-height: 1.05; }
        .reference {
            margin-top: {{ .55 * $echelleContenu }}mm;
            overflow: hidden;
            font-size: {{ 4.4 * $echelleContenu }}pt;
            font-weight: bold;
            line-height: 1.05;
            white-space: nowrap;
        }
        .reference-label { font-weight: normal; opacity: .58; }
        .code {
            margin-top: {{ 1 * $echelleContenu }}mm;
            overflow: hidden;
            font-family: DejaVu Sans, sans-serif;
            font-size: {{ 5.2 * $echelleContenu }}pt;
            font-weight: bold;
            line-height: 1.1;
            letter-spacing: {{ .1 * $echelleContenu }}pt;
            white-space: nowrap;
            text-align: left;
        }
        @if($configuration['compact_layout'])
        .qr { left: {{ 1.3 * $echelleContenu }}mm; padding: {{ .25 * $echelleContenu }}mm; }
        .infos { right: {{ 1.2 * $echelleContenu }}mm; left: {{ $tailleQrRendue + (3 * $echelleContenu) }}mm; }
        .designation { font-size: {{ 5.1 * $echelleContenu }}pt; line-height: 1.03; }
        .composant { margin-top: {{ .45 * $echelleContenu }}mm; font-size: {{ 4.2 * $echelleContenu }}pt; }
        .champ-label { margin-top: {{ .8 * $echelleContenu }}mm; font-size: {{ 3 * $echelleContenu }}pt; letter-spacing: {{ .1 * $echelleContenu }}pt; }
        .site { margin-top: {{ .15 * $echelleContenu }}mm; font-size: {{ 4.1 * $echelleContenu }}pt; }
        .reference { margin-top: {{ .35 * $echelleContenu }}mm; font-size: {{ 3.7 * $echelleContenu }}pt; }
        .code { margin-top: {{ .7 * $echelleContenu }}mm; font-size: {{ 4.4 * $echelleContenu }}pt; }
        @endif
        @if($configuration['narrow_layout'])
        .code { font-size: {{ 4 * $echelleContenu }}pt; letter-spacing: 0; }
        @endif
        @if($configuration['short_layout'])
        .qr { left: {{ 1.2 * $echelleContenu }}mm; padding: {{ .2 * $echelleContenu }}mm; }
        .designation { font-size: {{ 5 * $echelleContenu }}pt; line-height: 1.02; }
        .composant { margin-top: {{ .35 * $echelleContenu }}mm; font-size: {{ 4 * $echelleContenu }}pt; }
        .champ-label { margin-top: {{ .55 * $echelleContenu }}mm; font-size: {{ 2.8 * $echelleContenu }}pt; }
        .site { margin-top: {{ .1 * $echelleContenu }}mm; font-size: {{ 3.8 * $echelleContenu }}pt; }
        .reference { margin-top: {{ .25 * $echelleContenu }}mm; font-size: {{ 3.4 * $echelleContenu }}pt; }
        .code { margin-top: {{ .55 * $echelleContenu }}mm; }
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
                // Le QR est centré verticalement ; les informations restent dans un
                // même flux afin que le code suive immédiatement le nom du site.
                $margeQr = $configuration['narrow_layout']
                    ? 1.2
                    : max(.8, ($hauteurContenu - $tailleQrRendue) / 2);
            @endphp
            @if($etiquette)
            <article class="etiquette" style="left: {{ $gauche }}mm; top: {{ $haut }}mm;">
                <div class="contenu" style="left: {{ $retraitGauche }}mm; top: {{ $retraitHaut }}mm; width: {{ $configuration['label_width'] - $retraitGauche - $retraitDroite }}mm; height: {{ $hauteurContenu }}mm;">
                    <div class="qr" style="top: {{ $margeQr }}mm;"><img src="data:image/svg+xml;base64,{{ $etiquette['qr_base64'] }}" alt="QR code"></div>
                    <div class="infos" style="top: {{ $margeQr + .3 }}mm;">
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
                        @if(!empty($etiquette['reference']))
                            <div class="reference">
                                <span class="reference-label">{{ $etiquette['reference']['libelle'] }} :</span>
                                {{ $etiquette['reference']['valeur'] }}
                            </div>
                        @endif
                        @if($configuration['show_code'])
                            {{-- Code d'inventaire en clair, juste sous le site. --}}
                            <div class="code">{{ $etiquette['code_affiche'] ?? $etiquette['code'] }}</div>
                        @endif
                    </div>
                </div>
            </article>
            @endif
        @endforeach
    </section>
@endforeach
</body>
</html>

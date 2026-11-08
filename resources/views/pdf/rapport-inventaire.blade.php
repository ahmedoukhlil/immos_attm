<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport Inventaire {{ $inventaire->annee }}</title>
    <style>
        @page { margin: 1.2cm; size: A4 landscape; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; line-height: 1.3; color: #333; }

        .header { position: fixed; top: -1cm; left: 0; right: 0; text-align: center; font-size: 7pt; color: #666; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        .footer { position: fixed; bottom: -1cm; left: 0; right: 0; text-align: center; font-size: 7pt; color: #666; border-top: 1px solid #ccc; padding-top: 3px; }
        .page-number:after { content: "Page " counter(page) " / " counter(pages); }

        .cover-page { text-align: center; padding: 60px 40px; page-break-after: always; }
        .cover-title { font-size: 18pt; font-weight: bold; margin-bottom: 8px; color: #1e293b; }
        .cover-subtitle { font-size: 12pt; color: #64748b; margin-bottom: 30px; }
        .cover-badge { display: inline-block; padding: 8px 24px; font-weight: bold; font-size: 10pt; margin: 15px 0; color: white; }
        .badge-green { background: #16a34a; }
        .badge-red { background: #dc2626; }
        .badge-orange { background: #ea580c; }

        .cover-stats { margin: 30px auto; max-width: 600px; }
        .cover-stats table { width: 100%; border-collapse: collapse; }
        .cover-stats td { padding: 8px 12px; text-align: center; border: 1px solid #e2e8f0; }
        .cover-stats .val { font-size: 16pt; font-weight: bold; display: block; }
        .cover-stats .lbl { font-size: 7pt; color: #64748b; }
        .val-green { color: #16a34a; }
        .val-orange { color: #ea580c; }
        .val-red { color: #dc2626; }
        .val-blue { color: #2563eb; }

        .cover-info { margin: 20px auto; max-width: 500px; text-align: left; font-size: 9pt; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .cover-info p { margin-bottom: 4px; }

        h1 { font-size: 12pt; font-weight: bold; color: #1e293b; border-bottom: 2px solid #3b82f6; padding-bottom: 4px; margin: 18px 0 10px 0; page-break-after: avoid; }

        /* Site block */
        .emp-block { margin-bottom: 14px; page-break-inside: avoid; }
        .emp-header { background: #1e293b; color: white; padding: 6px 10px; font-size: 9pt; font-weight: bold; }
        .emp-header span { font-weight: normal; font-size: 8pt; color: #94a3b8; }
        .emp-summary { background: #f1f5f9; padding: 5px 10px; font-size: 7.5pt; color: #475569; border: 1px solid #e2e8f0; border-top: none; }
        .emp-summary .ok { color: #16a34a; font-weight: bold; }
        .emp-summary .ko { color: #dc2626; font-weight: bold; }
        .emp-summary .dep { color: #ea580c; font-weight: bold; }

        table.rapport { width: 100%; border-collapse: collapse; margin: 0; font-size: 7.5pt; }
        table.rapport th { background: #e2e8f0; color: #1e293b; font-weight: bold; padding: 4px 6px; border: 1px solid #cbd5e1; text-align: left; }
        table.rapport td { padding: 3px 6px; border: 1px solid #e2e8f0; }
        table.rapport tbody tr:nth-child(even) { background: #f8fafc; }
        table.rapport .text-center { text-align: center; }

        /* Status cells */
        .st-present { background: #dcfce7; color: #166534; font-weight: bold; text-align: center; }
        .st-absent { background: #fee2e2; color: #991b1b; font-weight: bold; text-align: center; }
        .st-deplace { background: #fef3c7; color: #92400e; font-weight: bold; text-align: center; }
        .st-nonscan { background: #f1f5f9; color: #64748b; text-align: center; }
        .st-oui { background: #dcfce7; color: #166534; text-align: center; font-weight: bold; }
        .st-non { background: #fee2e2; color: #991b1b; text-align: center; font-weight: bold; }

        .deplace-separator { background: #fef3c7; }
        .deplace-separator td { font-weight: bold; color: #92400e; font-size: 7pt; padding: 3px 6px; }

        .etat-defectueux { color: #dc2626; font-weight: bold; }
        .etat-neuf { color: #16a34a; }
        .etat-bon { color: #2563eb; }

        .toc { page-break-after: always; }
        .toc ul { list-style: none; margin-left: 0; }
        .toc li { padding: 4px 0; border-bottom: 1px dotted #cbd5e1; font-size: 9pt; }

        .page-break { page-break-after: always; }
        .no-break { page-break-inside: avoid; }

        .anomalies-box { padding: 8px 10px; margin: 8px 0; border: 1px solid #e2e8f0; border-left-width: 3px; page-break-inside: avoid; }
        .anomalies-warning { background: #fffbeb; border-left-color: #f59e0b; }
        .anomalies-danger { background: #fef2f2; border-left-color: #ef4444; }
        .anomalies-info { background: #f0f9ff; border-left-color: #3b82f6; }
        ul { margin: 4px 0 4px 16px; }
        li { margin-bottom: 2px; }
    </style>
</head>
<body>

    <div class="header">
        SAN — Rapport de rapprochement inventaire {{ $inventaire->annee }} | Édité le {{ now()->format('d/m/Y à H:i') }}
    </div>
    <div class="footer">
        <span class="page-number"></span>
    </div>

    {{-- ========== PAGE DE COUVERTURE ========== --}}
    <div class="cover-page">
        <div class="cover-title">Rapport de Rapprochement</div>
        <div class="cover-title" style="font-size:14pt;">Inventaire {{ $inventaire->annee }}</div>
        <div class="cover-subtitle">{{ ($inventaire->started_at ?? $inventaire->date_debut)->format('d/m/Y') }} — {{ ($inventaire->finished_at ?? $inventaire->date_fin)?->format('d/m/Y') ?? 'En cours' }}</div>

        @php
            $taux = $statistiques['taux_conformite'] ?? 0;
            $badgeClass = $taux >= 90 ? 'badge-green' : ($taux >= 70 ? 'badge-orange' : 'badge-red');
            $badgeText = $taux >= 90 ? 'Conforme' : ($taux >= 70 ? 'À améliorer' : 'Non conforme');
        @endphp
        <div class="cover-badge {{ $badgeClass }}">{{ $badgeText }} — {{ number_format($taux, 1) }}%</div>

        <div class="cover-stats">
            <table>
                <tr>
                    <td>
                        <span class="val val-blue">{{ number_format($statistiques['total_biens_attendus'] ?? 0) }}</span>
                        <span class="lbl">Biens attendus</span>
                    </td>
                    <td>
                        <span class="val val-green">{{ number_format($statistiques['biens_presents'] ?? 0) }}</span>
                        <span class="lbl">Présents</span>
                    </td>
                    <td>
                        <span class="val val-orange">{{ number_format($statistiques['biens_deplaces'] ?? 0) }}</span>
                        <span class="lbl">Déplacés</span>
                    </td>
                    <td>
                        <span class="val val-red">{{ number_format($statistiques['biens_absents'] ?? 0) }}</span>
                        <span class="lbl">Absents / Manquants</span>
                    </td>
                </tr>
            </table>
        </div>

        <div class="cover-info">
            <p><strong>Créé par :</strong> {{ $inventaire->creator->users ?? 'N/A' }}</p>
            @if($inventaire->closer)
            <p><strong>Clôturé par :</strong> {{ $inventaire->closer->users ?? 'N/A' }}</p>
            @endif
            <p><strong>Sites inventoriés :</strong> {{ count($detailParSite ?? []) }}</p>
            <p><strong>Rapprochement :</strong> {{ $statistiques['taux_rapprochement'] ?? 0 }}%</p>
            <p><strong>Couverture physique :</strong> {{ $statistiques['taux_couverture'] ?? 0 }}%</p>
        </div>
    </div>

    {{-- ========== SOMMAIRE ========== --}}
    <div class="toc">
        <h1>Sommaire</h1>
        <ul>
            <li><strong>1.</strong> Synthèse globale</li>
            <li><strong>2.</strong> Rapprochement par site ({{ count($detailParSite ?? []) }} sites)</li>
            <li><strong>3.</strong> Anomalies et recommandations</li>
        </ul>

        @if(count($detailParSite ?? []) > 0)
        <h1 style="margin-top: 20px; font-size: 10pt;">Index des sites</h1>
        <table class="rapport" style="font-size: 7.5pt;">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 20%;">Site</th>
                    <th style="width: 20%;">Localisation</th>
                    <th style="width: 15%;">Responsable</th>
                    <th class="text-center" style="width: 8%;">Attendus</th>
                    <th class="text-center" style="width: 8%;">Trouvés</th>
                    <th class="text-center" style="width: 8%;">Manquants</th>
                    <th class="text-center" style="width: 8%;">Déplacés ici</th>
                    <th class="text-center" style="width: 8%;">Conformité</th>
                </tr>
            </thead>
            <tbody>
                @foreach($detailParSite as $i => $emp)
                @php
                    $txClass = $emp['taux_conformite'] >= 90 ? 'val-green' : ($emp['taux_conformite'] >= 70 ? 'val-orange' : 'val-red');
                @endphp
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td><strong>{{ $emp['designation'] }}</strong></td>
                    <td>{{ $emp['localisation'] }}</td>
                    <td>{{ $emp['responsable'] ?? '-' }}</td>
                    <td class="text-center">{{ $emp['total_attendus'] }}</td>
                    <td class="text-center">{{ $emp['total_trouves'] }}</td>
                    <td class="text-center">{{ $emp['total_manquants'] }}</td>
                    <td class="text-center">{{ $emp['total_deplaces_ici'] }}</td>
                    <td class="text-center" style="{{ $txClass }}"><strong>{{ $emp['taux_conformite'] }}%</strong></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- ========== SECTION 1: SYNTHÈSE ========== --}}
    <h1>1. Synthèse globale</h1>

    <table class="rapport" style="margin-bottom: 15px;">
        <tr>
            <td style="width: 25%; text-align: center; padding: 10px;">
                <span style="font-size: 16pt; font-weight: bold; color: #2563eb;">{{ number_format($statistiques['total_biens_attendus'] ?? 0) }}</span><br>
                <span style="font-size: 7pt; color: #64748b;">Biens attendus</span>
            </td>
            <td style="width: 25%; text-align: center; padding: 10px;">
                <span style="font-size: 16pt; font-weight: bold; color: #16a34a;">{{ number_format($statistiques['total_biens_scannes'] ?? 0) }}</span><br>
                <span style="font-size: 7pt; color: #64748b;">Biens scannés</span>
            </td>
            <td style="width: 25%; text-align: center; padding: 10px;">
                <span style="font-size: 16pt; font-weight: bold; color: #ea580c;">{{ number_format($statistiques['biens_deplaces'] ?? 0) }}</span><br>
                <span style="font-size: 7pt; color: #64748b;">Déplacés</span>
            </td>
            <td style="width: 25%; text-align: center; padding: 10px;">
                <span style="font-size: 16pt; font-weight: bold; color: #dc2626;">{{ number_format($statistiques['biens_absents'] ?? 0) }}</span><br>
                <span style="font-size: 7pt; color: #64748b;">Absents</span>
            </td>
        </tr>
    </table>

    <p style="font-size: 8pt; margin-bottom: 10px;">
        <strong>Taux de conformité :</strong> {{ number_format($statistiques['taux_conformite'] ?? 0, 1) }}% |
        <strong>Rapprochement :</strong> {{ $statistiques['taux_rapprochement'] ?? 0 }}% |
        <strong>Couverture physique :</strong> {{ $statistiques['taux_couverture'] ?? 0 }}% |
        <strong>Anomalies uniques :</strong> {{ $statistiques['taux_anomalies'] ?? 0 }}% |
        <strong>Biens défectueux :</strong> {{ $statistiques['biens_defectueux'] ?? 0 }}
    </p>

    {{-- ========== SECTION 2: RAPPROCHEMENT PAR SITE ========== --}}
    <div class="page-break"></div>
    <h1>2. Rapprochement par site</h1>

    @php $detailParSite = $detailParSite ?? []; @endphp
    @if(count($detailParSite) > 0)
        @foreach($detailParSite as $idx => $emp)
        <div class="emp-block">
            <div class="emp-header">
                {{ $emp['designation'] }} — {{ $emp['localisation'] }}
                <span> | {{ $emp['responsable'] ?? '' }} | Code: {{ $emp['code'] }}</span>
            </div>
            <div class="emp-summary">
                Attendus : <strong>{{ $emp['total_attendus'] }}</strong> |
                Trouvés : <span class="ok">{{ $emp['total_trouves'] }}</span> |
                Manquants : <span class="ko">{{ $emp['total_manquants'] }}</span> |
                Déplacés trouvés ici : <span class="dep">{{ $emp['total_deplaces_ici'] }}</span> |
                Conformité : <strong>{{ $emp['taux_conformite'] }}%</strong>
            </div>

            @if(count($emp['lignes_attendues']) > 0 || count($emp['lignes_deplaces']) > 0)
            <table class="rapport">
                <thead>
                    <tr>
                        <th style="width: 8%;">N° Ordre</th>
                        <th style="width: 30%;">Désignation</th>
                        <th style="width: 14%;">Catégorie</th>
                        <th class="text-center" style="width: 8%;">Attendu</th>
                        <th class="text-center" style="width: 8%;">Trouvé</th>
                        <th class="text-center" style="width: 10%;">État</th>
                        <th style="width: 22%;">Observation</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Biens attendus --}}
                    @foreach($emp['lignes_attendues'] as $ligne)
                    @php
                        $obsClass = match($ligne['observation']) {
                            'Présent' => 'st-present',
                            'Absent' => 'st-absent',
                            'Déplacé' => 'st-deplace',
                            default => 'st-nonscan',
                        };
                        $etatClass = match($ligne['etat']) {
                            'Defectueux' => 'etat-defectueux',
                            'Neuf' => 'etat-neuf',
                            'Bon Etat' => 'etat-bon',
                            default => '',
                        };
                    @endphp
                    <tr>
                        <td>{{ $ligne['code'] }}</td>
                        <td>{{ Str::limit($ligne['designation'], 45) }}</td>
                        <td>{{ Str::limit($ligne['categorie'], 20) }}</td>
                        <td class="st-oui">Oui</td>
                        <td class="{{ $ligne['trouve'] === 'Oui' ? 'st-oui' : 'st-non' }}">{{ $ligne['trouve'] }}</td>
                        <td class="text-center {{ $etatClass }}">{{ $ligne['etat'] }}</td>
                        <td class="{{ $obsClass }}">{{ $ligne['observation'] }}</td>
                    </tr>
                    @endforeach

                    {{-- Séparateur si biens déplacés trouvés ici --}}
                    @if(count($emp['lignes_deplaces']) > 0)
                    <tr class="deplace-separator">
                        <td colspan="7">▸ Biens déplacés trouvés dans ce site ({{ count($emp['lignes_deplaces']) }})</td>
                    </tr>
                    @foreach($emp['lignes_deplaces'] as $ligne)
                    @php
                        $etatClass = match($ligne['etat']) {
                            'Defectueux' => 'etat-defectueux',
                            'Neuf' => 'etat-neuf',
                            'Bon Etat' => 'etat-bon',
                            default => '',
                        };
                    @endphp
                    <tr style="background: #fef9c3;">
                        <td>{{ $ligne['code'] }}</td>
                        <td>{{ Str::limit($ligne['designation'], 45) }}</td>
                        <td>{{ Str::limit($ligne['categorie'], 20) }}</td>
                        <td class="st-non">Non</td>
                        <td class="st-oui">Oui</td>
                        <td class="text-center {{ $etatClass }}">{{ $ligne['etat'] }}</td>
                        <td class="st-deplace">{{ $ligne['observation'] }}</td>
                    </tr>
                    @endforeach
                    @endif
                </tbody>
            </table>
            @else
            <p style="font-size: 7.5pt; color: #94a3b8; padding: 6px 10px; font-style: italic;">Aucune immobilisation dans ce site.</p>
            @endif
        </div>

        {{-- Saut de page tous les 3 sites pour lisibilité --}}
        @if(($idx + 1) % 2 === 0 && $idx + 1 < count($detailParSite))
        <div class="page-break"></div>
        @endif
        @endforeach
    @else
    <div class="anomalies-warning">
        <p>Aucun site inventorié. Vérifiez que les scans ont été effectués via la PWA.</p>
    </div>
    @endif

    {{-- ========== SECTION 3: ANOMALIES ========== --}}
    @if(isset($anomalies) || isset($recommendations))
    <div class="page-break"></div>
    <h1>3. Anomalies et recommandations</h1>

    @php
        $totalAnomalies = count($anomalies['localisations_non_demarrees'] ?? []) +
                         count($anomalies['taux_absence_eleve'] ?? []) +
                         count($anomalies['biens_defectueux'] ?? []);
    @endphp

    @if($totalAnomalies > 0)
        @if(count($anomalies['biens_defectueux'] ?? []) > 0)
        <h2 style="font-size: 9pt; margin: 10px 0 6px 0;">Biens signalés défectueux ({{ count($anomalies['biens_defectueux']) }})</h2>

        @foreach($anomalies['biens_defectueux'] as $b)
        <div class="no-break" style="border: 1px solid #fca5a5; margin-bottom: 10px; background: #fef2f2;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 10px; vertical-align: top; {{ !empty($b['photo_absolute']) ? 'width: 70%;' : 'width: 100%;' }}">
                        <p style="font-size: 9pt; font-weight: bold; color: #991b1b; margin-bottom: 4px;">{{ $b['code'] ?? 'N/A' }} — {{ $b['designation'] ?? 'N/A' }}</p>
                        <p style="font-size: 8pt; color: #7f1d1d;">Localisation : {{ $b['localisation'] ?? 'N/A' }}</p>
                        <p style="font-size: 7pt; color: #dc2626; margin-top: 3px; font-weight: bold;">DEFECTUEUX</p>
                    </td>
                    @if(!empty($b['photo_absolute']))
                    <td style="padding: 6px; vertical-align: top; width: 30%; text-align: center;">
                        <img src="{{ $b['photo_absolute'] }}" style="max-width: 180px; max-height: 120px; border: 2px solid #fca5a5; object-fit: contain;">
                        <p style="font-size: 6pt; color: #9ca3af; margin-top: 2px;">Photo prise lors du scan</p>
                    </td>
                    @endif
                </tr>
            </table>
        </div>
        @endforeach
        @endif

        @if(count($anomalies['localisations_non_demarrees'] ?? []) > 0)
        <h2 style="font-size: 9pt; margin: 10px 0 6px 0;">Localisations non démarrées</h2>
        <div class="anomalies-warning">
            <ul>
                @foreach($anomalies['localisations_non_demarrees'] as $loc)
                <li>{{ $loc['code'] ?? 'N/A' }} — {{ $loc['designation'] ?? 'N/A' }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @if(count($anomalies['taux_absence_eleve'] ?? []) > 0)
        <h2 style="font-size: 9pt; margin: 10px 0 6px 0;">Taux d'absence élevé</h2>
        <div class="anomalies-danger">
            <ul>
                @foreach($anomalies['taux_absence_eleve'] as $a)
                <li>{{ $a['code'] ?? 'N/A' }} — {{ $a['taux_absence'] ?? 0 }}% absents ({{ $a['biens_absents'] ?? 0 }} biens)</li>
                @endforeach
            </ul>
        </div>
        @endif
    @else
    <div class="anomalies-info">
        <p>Aucune anomalie majeure détectée.</p>
    </div>
    @endif

    @if(isset($recommendations))
        @if(count($recommendations['corrections_immediates'] ?? []) > 0)
        <h2 style="font-size: 9pt; margin: 10px 0 6px 0;">Corrections immédiates</h2>
        <div class="anomalies-danger">
            <ul>
                @foreach($recommendations['corrections_immediates'] as $r)
                <li>{{ $r }}</li>
                @endforeach
            </ul>
        </div>
        @endif
        @if(count($recommendations['ameliorations_organisationnelles'] ?? []) > 0)
        <h2 style="font-size: 9pt; margin: 10px 0 6px 0;">Améliorations organisationnelles</h2>
        <div class="anomalies-info">
            <ul>
                @foreach($recommendations['ameliorations_organisationnelles'] as $r)
                <li>{{ $r }}</li>
                @endforeach
            </ul>
        </div>
        @endif
    @endif
    @endif

</body>
</html>

<x-layouts.app>
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ route('sites.show', $site) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">← Retour au site</a>
            <h1 class="mt-2 text-2xl font-bold text-gray-900 sm:text-3xl">Étiquettes - {{ $site->site }}</h1>
            <p class="mt-1 text-sm text-gray-500">Composez une planche d’étiquettes QR sur A4, A5 ou au format individuel.</p>
        </div>
        <a href="{{ route('biens.index', ['filterSite' => $site->id]) }}" class="inline-flex justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Voir les biens</a>
    </div>

    @if(session('success'))
        <div role="status" class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">La configuration contient des erreurs :</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach([
            ['label' => 'Immobilisations', 'value' => $resume['immobilisations'], 'color' => 'text-gray-900'],
            ['label' => 'Composants à étiqueter', 'value' => $resume['composants'], 'color' => 'text-indigo-700'],
            ['label' => 'Total des étiquettes', 'value' => $resume['total'], 'color' => 'text-indigo-700'],
            ['label' => 'Nouvelles à imprimer', 'value' => $resume['nouvelles'], 'color' => 'text-amber-700'],
        ] as $stat)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $stat['label'] }}</p>
                <p class="mt-2 text-3xl font-bold {{ $stat['color'] }}">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('sites.etiquettes.pdf', $site) }}" target="_blank"
        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
        x-data="{
            printMode: @js(old('mode', $resume['nouvelles'] > 0 ? 'nouvelles' : 'complete')),
            format: @js(old('page_format', 'a4_portrait')),
            gridMode: @js(old('grid_mode', 'manual')),
            width: Number(@js(old('label_width', 52.5))), height: Number(@js(old('label_height', 21.2))),
            manualColumns: Number(@js(old('columns', 4))), manualRows: Number(@js(old('rows', 14))),
            margin: Number(@js(old('page_margin', 0))), gapX: Number(@js(old('gap_x', 0))), gapY: Number(@js(old('gap_y', 0))),
            offsetX: Number(@js(old('offset_x', 0))), offsetY: Number(@js(old('offset_y', 0))),
            printerMargin: Number(@js(old('printer_margin', 4))),
            startPosition: Number(@js(old('start_position', 1))),
            qrSize: Number(@js(old('qr_size', 12))),
            contentScale: Number(@js(old('content_scale', 100))),
            bg: '#FFFFFF', text: '#111827',
            showDesignation: true, showEmplacement: true, showCode: true,
            get pageWidth() { return this.format === 'a4_paysage' ? 297 : (this.format === 'a5_portrait' ? 148 : (this.format === 'a5_paysage' ? 210 : (this.format === 'individuelle' ? this.width : 210))); },
            get pageHeight() { return this.format === 'a4_paysage' ? 210 : (this.format === 'a5_portrait' ? 210 : (this.format === 'a5_paysage' ? 148 : (this.format === 'individuelle' ? this.height : 297))); },
            get effectiveMargin() { return this.format === 'individuelle' ? 0 : Number(this.margin); },
            get effectiveGapX() { return this.format === 'individuelle' ? 0 : Number(this.gapX); },
            get effectiveGapY() { return this.format === 'individuelle' ? 0 : Number(this.gapY); },
            get manualGrid() { return this.gridMode === 'manual' && this.format !== 'individuelle'; },
            get columns() { return this.manualGrid ? Math.max(1, Number(this.manualColumns)) : Math.max(1, Math.floor((this.pageWidth - 2 * this.effectiveMargin + this.effectiveGapX) / (Number(this.width) + this.effectiveGapX))); },
            get rows() { return this.manualGrid ? Math.max(1, Number(this.manualRows)) : Math.max(1, Math.floor((this.pageHeight - 2 * this.effectiveMargin + this.effectiveGapY) / (Number(this.height) + this.effectiveGapY))); },
            get effectiveLabelWidth() { return this.manualGrid ? Math.max(1, (this.pageWidth - 2 * this.effectiveMargin - (this.columns - 1) * this.effectiveGapX) / this.columns) : Number(this.width); },
            get effectiveLabelHeight() { return this.manualGrid ? Math.max(1, (this.pageHeight - 2 * this.effectiveMargin - (this.rows - 1) * this.effectiveGapY) / this.rows) : Number(this.height); },
            get horizontalLabel() { return this.effectiveLabelWidth > this.effectiveLabelHeight; },
            get capacity() { return this.columns * this.rows; },
            get remainingOnFirstPage() { return Math.max(0, this.capacity - Number(this.startPosition) + 1); },
            applyHorizontal56Preset() { this.format = 'a4_portrait'; this.gridMode = 'manual'; this.manualColumns = 4; this.manualRows = 14; this.margin = 2; this.gapX = 0; this.gapY = 0; this.qrSize = 12; }
        }"
        x-effect="if (startPosition > capacity) startPosition = capacity">
        @csrf
        <div class="border-b border-gray-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-gray-900">Générateur groupé QR dynamique</h2>
            <p class="mt-1 text-sm text-gray-500">Configurez la planche, contrôlez l’aperçu puis générez le PDF.</p>
        </div>

        @if($resume['nouvelles'] > 0)
            <div class="border-b border-amber-200 bg-amber-50 px-5 py-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-semibold text-amber-900">
                            {{ $resume['nouvelles'] }} nouvelle{{ $resume['nouvelles'] > 1 ? 's' : '' }} étiquette{{ $resume['nouvelles'] > 1 ? 's' : '' }} à imprimer
                        </p>
                        <p class="mt-1 text-sm text-amber-800">
                            Les étiquettes déjà présentes dans l’historique de cet emplacement seront automatiquement exclues.
                        </p>
                    </div>
                    <button
                        type="submit"
                        @click="printMode = 'nouvelles'"
                        class="shrink-0 rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">
                        Générer le PDF des nouvelles
                    </button>
                </div>
            </div>
        @else
            <div class="border-b border-green-200 bg-green-50 px-5 py-3 text-sm text-green-800">
                Toutes les étiquettes actuelles de cet emplacement figurent déjà dans l’historique.
            </div>
        @endif

        <div class="grid gap-8 p-5 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="text-sm font-medium text-gray-700">Biens à inclure
                        <select name="mode" x-model="printMode" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="complete">Toutes les étiquettes ({{ $resume['total'] }})</option>
                            <option value="nouvelles" @disabled($resume['nouvelles'] === 0)>Nouvelles uniquement ({{ $resume['nouvelles'] }})</option>
                            <option value="selection">Sélection ci-dessous</option>
                        </select>
                        <span class="mt-1 block text-xs font-normal text-gray-500">« Nouvelles uniquement » exclut tout code déjà généré pour cet emplacement.</span>
                    </label>
                    <label class="text-sm font-medium text-gray-700">Format de page
                        <select name="page_format" x-model="format" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($formatsPage as $valeur => $format)
                                <option value="{{ $valeur }}" @selected($valeur === 'a4_portrait')>{{ $format['libelle'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-medium text-gray-700">Calcul de la grille
                        <select name="grid_mode" x-model="gridMode" :disabled="format === 'individuelle'" class="mt-1 block w-full rounded-lg border-gray-300 text-sm disabled:bg-gray-100 focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="dimensions">Automatique selon les dimensions</option>
                            <option value="manual">Nombre de lignes et colonnes</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium text-gray-700">Taille du QR (mm)
                        <input name="qr_size" x-model.number="qrSize" type="number" min="12" max="45" step="1" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                        <span class="mt-1 block text-xs font-normal text-gray-500">12 mm minimum recommandé pour une lecture fiable.</span>
                    </label>
                    <label class="text-sm font-medium text-gray-700 sm:col-span-2">Taille du contenu
                        <div class="mt-1 flex items-center gap-3">
                            <input name="content_scale" x-model.number="contentScale" type="range" min="100" max="125" step="5" class="h-2 w-full cursor-pointer accent-indigo-600">
                            <output class="w-14 rounded-md bg-indigo-50 px-2 py-1 text-center text-sm font-semibold text-indigo-700" x-text="`${contentScale}%`"></output>
                        </div>
                        <span class="mt-1 block text-xs font-normal text-gray-500">Agrandit le QR et les textes sans dépasser la largeur et la hauteur calculées de l’étiquette.</span>
                    </label>
                    <label x-show="manualGrid" class="text-sm font-medium text-gray-700">Étiquettes par ligne (colonnes)
                        <input name="columns" x-model.number="manualColumns" :disabled="!manualGrid" type="number" min="1" max="12" step="1" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label x-show="manualGrid" class="text-sm font-medium text-gray-700">Nombre de lignes
                        <input name="rows" x-model.number="manualRows" :disabled="!manualGrid" type="number" min="1" max="20" step="1" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                        <span class="mt-1 block text-xs font-normal text-gray-500"><strong x-text="capacity"></strong> étiquettes par page.</span>
                    </label>
                    <div x-show="manualGrid" class="rounded-lg border p-3 text-sm sm:col-span-2 lg:col-span-3"
                        :class="horizontalLabel ? 'border-green-200 bg-green-50 text-green-800' : 'border-amber-200 bg-amber-50 text-amber-900'">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p>
                                <strong x-text="horizontalLabel ? 'Format horizontal' : 'Format vertical'"></strong>
                                <span> - dimensions calculées : </span>
                                <span x-text="effectiveLabelWidth.toFixed(1)"></span> × <span x-text="effectiveLabelHeight.toFixed(1)"></span> mm.
                            </p>
                            <button type="button" @click="applyHorizontal56Preset()" class="shrink-0 rounded-lg border border-indigo-200 bg-white px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Appliquer 56 horizontales (4 × 14)</button>
                        </div>
                    </div>
                    <label class="text-sm font-medium text-gray-700">Première case libre
                        <input name="start_position" x-model.number="startPosition" type="number" min="1" :max="capacity" value="1" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                        <span class="mt-1 block text-xs font-normal text-gray-500">Ex. 21 si les cases 1 à 20 sont déjà réservées.</span>
                    </label>
                    <label class="text-sm font-medium text-gray-700">Largeur étiquette (mm)
                        <input name="label_width" :value="manualGrid ? effectiveLabelWidth.toFixed(1) : width" @input="if (!manualGrid) width = Number($event.target.value)" :disabled="manualGrid" type="number" min="20" max="100" step="0.1" class="mt-1 block w-full rounded-lg border-gray-300 text-sm disabled:bg-gray-100">
                        <span x-show="manualGrid" class="mt-1 block text-xs font-normal text-gray-500">Calculée : <span x-text="effectiveLabelWidth.toFixed(1)"></span> mm</span>
                    </label>
                    <label class="text-sm font-medium text-gray-700">Hauteur étiquette (mm)
                        <input name="label_height" :value="manualGrid ? effectiveLabelHeight.toFixed(1) : height" @input="if (!manualGrid) height = Number($event.target.value)" :disabled="manualGrid" type="number" min="15" max="80" step="0.1" class="mt-1 block w-full rounded-lg border-gray-300 text-sm disabled:bg-gray-100">
                        <span x-show="manualGrid" class="mt-1 block text-xs font-normal text-gray-500">Calculée : <span x-text="effectiveLabelHeight.toFixed(1)"></span> mm</span>
                    </label>
                    <label class="text-sm font-medium text-gray-700">Marge de page (mm)
                        <input name="page_margin" x-model.number="margin" type="number" min="0" max="20" step="0.5" value="0" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-gray-700">Espacement horizontal (mm)
                        <input name="gap_x" x-model.number="gapX" type="number" min="0" max="10" step="0.5" value="0" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-gray-700">Espacement vertical (mm)
                        <input name="gap_y" x-model.number="gapY" type="number" min="0" max="10" step="0.5" value="0" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-gray-700">Décalage horizontal (mm)
                        <input name="offset_x" x-model.number="offsetX" :disabled="format === 'individuelle'" type="number" min="-10" max="10" step="0.1" value="0" class="mt-1 block w-full rounded-lg border-gray-300 text-sm disabled:bg-gray-100">
                        <span class="mt-1 block text-xs font-normal text-gray-500">Positif = vers la droite. À régler après une impression test.</span>
                    </label>
                    <label class="text-sm font-medium text-gray-700">Décalage vertical (mm)
                        <input name="offset_y" x-model.number="offsetY" :disabled="format === 'individuelle'" type="number" min="-10" max="10" step="0.1" value="0" class="mt-1 block w-full rounded-lg border-gray-300 text-sm disabled:bg-gray-100">
                        <span class="mt-1 block text-xs font-normal text-gray-500">Positif = vers le bas. Imprimez en « Taille réelle » (100 %).</span>
                    </label>
                    <label class="text-sm font-medium text-gray-700">Zone non imprimable (mm)
                        <input name="printer_margin" x-model.number="printerMargin" :disabled="format === 'individuelle'" type="number" min="0" max="10" step="0.5" value="4" class="mt-1 block w-full rounded-lg border-gray-300 text-sm disabled:bg-gray-100">
                        <span class="mt-1 block text-xs font-normal text-gray-500">Bord que l’imprimante n’encre pas : le texte des étiquettes en bordure est rentré d’autant.</span>
                    </label>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Couleurs</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <label class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 p-3 text-sm">Fond <input name="background_color" x-model="bg" type="color" value="#FFFFFF" class="h-9 w-14 cursor-pointer rounded border-0 bg-transparent"></label>
                        <label class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 p-3 text-sm">Texte <input name="text_color" x-model="text" type="color" value="#111827" class="h-9 w-14 cursor-pointer rounded border-0 bg-transparent"></label>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Informations visibles</h3>
                    <div class="mt-3 grid gap-3 sm:grid-cols-3">
                        @foreach([
                            ['name' => 'show_designation', 'model' => 'showDesignation', 'label' => 'Désignation'],
                            ['name' => 'show_emplacement', 'model' => 'showEmplacement', 'label' => 'Site'],
                            ['name' => 'show_code', 'model' => 'showCode', 'label' => 'Code complet'],
                        ] as $option)
                            <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-3 text-sm text-gray-700">
                                <input type="hidden" name="{{ $option['name'] }}" value="0">
                                <input type="checkbox" name="{{ $option['name'] }}" value="1" x-model="{{ $option['model'] }}" checked class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                {{ $option['label'] }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <aside>
                <h3 class="text-sm font-semibold text-gray-900">Aperçu de l’étiquette</h3>
                <div class="mt-3 overflow-auto rounded-xl bg-gray-100 p-4">
                    <div class="relative mx-auto flex items-center overflow-hidden shadow-sm"
                        :style="`width: ${effectiveLabelWidth < 45 ? '220px' : '100%'}; max-width: 360px; aspect-ratio: ${effectiveLabelWidth}/${effectiveLabelHeight}; background:${bg}; color:${text}`">
                        <div class="flex items-center gap-3 px-3 py-2 transition-transform" :style="`transform: scale(${contentScale / 100}); transform-origin: center;`">
                            <div class="shrink-0 bg-white p-1"><img src="{{ $qrApercu }}" alt="Aperçu QR" class="h-[68px] w-[68px]"></div>
                            <div class="min-w-0 text-xs">
                                <p x-show="showDesignation" class="line-clamp-2 text-sm font-bold leading-tight">{{ $site->immobilisations->first()?->designation?->designation ?? 'Désignation du bien' }}</p>
                                <div x-show="showEmplacement" class="mt-2">
                                    <p class="text-[8px] font-bold uppercase tracking-wider opacity-40">Site</p>
                                    <p class="truncate">{{ $site->site }}</p>
                                </div>
                                <p x-show="showCode" class="mt-2 truncate text-[8px] opacity-60">{{ $site->immobilisations->first()?->code_formate ?? 'NJ/DES/CAT/2026/SF/001' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="mt-3 rounded-lg border border-blue-100 bg-blue-50 p-3 text-xs text-blue-800">La zone du QR reste blanche pour garantir sa lecture, même si le fond de l’étiquette est coloré.</p>
                <div class="mt-4 rounded-lg border border-gray-200 p-3">
                    <div class="flex items-center justify-between text-xs text-gray-600">
                        <span><strong x-text="capacity"></strong> cases par page</span>
                        <span><strong x-text="remainingOnFirstPage"></strong> cases disponibles sur la première page</span>
                    </div>
                    <div class="mt-3 grid gap-1" :style="`grid-template-columns: repeat(${columns}, minmax(0, 1fr))`">
                        <template x-for="position in capacity" :key="position">
                            <div class="flex aspect-square items-center justify-center rounded border text-[9px] font-medium"
                                :class="position < startPosition ? 'border-gray-300 bg-gray-300 text-gray-500' : (position === startPosition ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300 bg-white text-gray-600')"
                                x-text="position"></div>
                        </template>
                    </div>
                    <p class="mt-2 text-[11px] text-gray-500">Les cases grises seront laissées entièrement vides. L’impression commence sur la case bleue.</p>
                </div>
            </aside>
        </div>

        <details class="border-t border-gray-200">
            <summary class="cursor-pointer px-5 py-4 text-sm font-semibold text-gray-900">Choisir une sélection de biens</summary>
            <div class="max-h-96 divide-y divide-gray-100 overflow-y-auto border-t border-gray-100">
                @forelse($site->immobilisations as $bien)
                    @php $nombreEtiquettes = $bien->designation?->est_compose ? $bien->designation->composants->sum('quantite') : 1; @endphp
                    <label class="flex cursor-pointer items-start gap-3 px-5 py-4 hover:bg-gray-50">
                        <input type="checkbox" name="biens[]" value="{{ $bien->id }}" class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="min-w-0 flex-1"><span class="block font-medium text-gray-900">{{ $bien->libelle_article ?? 'Sans désignation' }}</span><span class="block truncate text-xs text-gray-500">{{ $bien->code_formate }}</span></span>
                        <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">{{ $nombreEtiquettes }} étiquette{{ $nombreEtiquettes > 1 ? 's' : '' }}</span>
                    </label>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-gray-500">Aucune immobilisation dans ce site.</p>
                @endforelse
            </div>
        </details>

        <div class="flex flex-col gap-3 border-t border-gray-200 bg-gray-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-gray-600">Imprimez le PDF à 100 %, sans option « Ajuster à la page ».</p>
            <button type="submit" @disabled($resume['total'] === 0) class="rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">Générer la planche QR</button>
        </div>
    </form>

    <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 px-5 py-4"><h2 class="text-lg font-semibold text-gray-900">Réimpression ciblée</h2><p class="text-sm text-gray-500">Réimprimez un bien complet ou seulement l’un de ses composants.</p></div>
        <div class="divide-y divide-gray-100">
            @foreach($site->immobilisations as $bien)
                <div class="flex flex-col gap-3 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0"><p class="font-medium text-gray-900">{{ $bien->libelle_article ?? 'Sans désignation' }}</p><p class="truncate text-xs text-gray-500">{{ $bien->code_formate }}</p></div>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('sites.etiquettes.pdf', $site) }}" target="_blank">@csrf<input type="hidden" name="mode" value="bien"><input type="hidden" name="bien" value="{{ $bien->id }}"><button class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">Tout réimprimer</button></form>
                        @foreach($bien->designation?->composants ?? [] as $composant)
                            <form method="POST" action="{{ route('sites.etiquettes.pdf', $site) }}" target="_blank">@csrf<input type="hidden" name="mode" value="composant"><input type="hidden" name="bien" value="{{ $bien->id }}"><input type="hidden" name="composant" value="{{ $composant->id }}"><button class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-medium text-indigo-700 hover:bg-indigo-100">{{ $composant->nom }}</button></form>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 px-5 py-4"><h2 class="text-lg font-semibold text-gray-900">Historique</h2></div>
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50"><tr><th class="px-5 py-3 text-left font-medium text-gray-500">Date</th><th class="px-5 py-3 text-left font-medium text-gray-500">Utilisateur</th><th class="px-5 py-3 text-left font-medium text-gray-500">Mode</th><th class="px-5 py-3 text-right font-medium text-gray-500">Étiquettes</th><th class="px-5 py-3 text-right font-medium text-gray-500">Statut</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($historique as $generation)
                    <tr><td class="whitespace-nowrap px-5 py-3 text-gray-700">{{ $generation['date']->format('d/m/Y H:i') }}</td><td class="px-5 py-3 text-gray-700">{{ $generation['utilisateur'] }}</td><td class="px-5 py-3 text-gray-700">{{ ucfirst($generation['mode']) }}</td><td class="px-5 py-3 text-right font-medium text-gray-900">{{ $generation['total'] }}</td><td class="px-5 py-3 text-right">
                        @if($generation['statut'] === 'imprimee')<span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700">Imprimée</span>@else<form method="POST" action="{{ route('sites.etiquettes.confirmer', [$site, $generation['uuid']]) }}">@csrf<button class="rounded-lg border border-green-200 bg-green-50 px-3 py-1.5 text-xs font-medium text-green-700 hover:bg-green-100">Confirmer l’impression</button></form>@endif
                    </td></tr>
                @empty<tr><td colspan="5" class="px-5 py-8 text-center text-gray-500">Aucune génération enregistrée.</td></tr>@endforelse
            </tbody>
        </table></div>
    </section>
</div>
</x-layouts.app>

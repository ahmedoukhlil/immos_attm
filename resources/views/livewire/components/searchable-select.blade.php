<div 
    x-data="{ 
        open: false,
        focusedIndex: -1,
        get filteredCount() {
            return {{ count($this->filteredOptions) }};
        },
        init() {
            this.$watch('open', value => {
                if (value) {
                    this.focusedIndex = -1;
                    this.$nextTick(() => {
                        this.$refs.searchInput?.focus();
                    });
                } else {
                    this.focusedIndex = -1;
                }
            });
        },
        selectFocused() {
            if (this.focusedIndex >= 0 && this.filteredCount > 0) {
                const options = this.$refs.dropdown?.querySelectorAll('[data-option-index]');
                if (options && options[this.focusedIndex]) {
                    options[this.focusedIndex].click();
                }
            }
        },
        moveFocus(direction) {
            if (direction === 'down') {
                this.focusedIndex = Math.min(this.focusedIndex + 1, this.filteredCount - 1);
            } else {
                this.focusedIndex = Math.max(this.focusedIndex - 1, -1);
            }
            this.scrollToFocused();
        },
        scrollToFocused() {
            this.$nextTick(() => {
                const options = this.$refs.dropdown?.querySelectorAll('[data-option-index]');
                if (options && options[this.focusedIndex]) {
                    options[this.focusedIndex].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }
            });
        }
    }"
    @click.outside="open = false"
    @keydown.escape="open = false"
    @keydown.arrow-down.prevent="open ? moveFocus('down') : (open = true)"
    @keydown.arrow-up.prevent="open && moveFocus('up')"
    @keydown.enter.prevent="open && selectFocused()"
    class="relative {{ $containerClass }}"
    style="z-index: 100;"
    :style="{ 'z-index': open ? '9999' : '100' }"
    role="group"
>
    {{-- Hidden input pour les formulaires --}}
    @if($name)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endif

    {{-- Bouton principal --}}
    <button
        type="button"
        @click="open = !open"
        :disabled="{{ $disabled ? 'true' : 'false' }}"
        class="relative w-full bg-white border border-gray-300 rounded-lg shadow-sm pl-3 {{ $allowClear && $value ? 'pr-16' : 'pr-10' }} py-2.5 text-left cursor-pointer focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-150 {{ $disabled ? 'opacity-50 cursor-not-allowed bg-gray-50' : 'hover:border-indigo-400 hover:shadow-md' }} {{ $inputClass }}"
        :class="{ 
            'ring-2 ring-indigo-500 border-indigo-500 shadow-md': open,
            'bg-indigo-50 border-indigo-300': @js($value) && !open
        }"
        role="combobox"
        aria-haspopup="listbox"
        :aria-expanded="open"
        aria-controls="searchable-options-{{ $this->getId() }}"
        aria-label="{{ $placeholder }}"
    >
        <span class="flex items-center">
            @if($value)
                <svg class="h-4 w-4 text-indigo-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
            @endif
            <span class="block truncate" :class="{ 'text-gray-500 italic': !@js($value), 'text-gray-900 font-medium': @js($value) }">
                {{ $this->selectedText }}
            </span>
        </span>
        
        {{-- Indicateur d'ouverture --}}
        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
            <svg 
                class="h-5 w-5 text-gray-400 transition-all duration-200"
                :class="{ 'rotate-180 text-indigo-500': open }"
                fill="none" 
                stroke="currentColor" 
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </span>
    </button>

    @if($allowClear && $value)
        <button
            type="button"
            wire:click.stop="clear"
            class="absolute right-8 top-1/2 z-10 -translate-y-1/2 rounded-md p-1 text-gray-400 transition-colors hover:bg-indigo-100 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            aria-label="Effacer la sélection"
            title="Effacer la sélection"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    @endif

    {{-- Dropdown --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        x-ref="dropdown"
        id="searchable-options-{{ $this->getId() }}"
        role="listbox"
        class="absolute mt-2 w-full bg-white shadow-xl rounded-xl border border-gray-200 overflow-hidden"
        style="max-height: 400px; z-index: 9999;"
    >
        {{-- Barre de recherche améliorée --}}
        <div class="sticky top-0 z-10 bg-white px-3 py-3 border-b border-gray-200">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input
                    type="text"
                    x-ref="searchInput"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ $searchPlaceholder }}"
                    class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm transition-all"
                    @click.stop
                >
                @if($search)
                    <button
                        type="button"
                        wire:click="$set('search', '')"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>
            {{-- Badge compteur --}}
            <div class="mt-2 flex items-center justify-between text-xs text-gray-500">
                <span class="flex items-center gap-1">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    {{ count($this->filteredOptions) }} résultat(s)
                </span>
                <span class="text-gray-400">
                    <kbd class="px-1.5 py-0.5 bg-gray-100 border border-gray-300 rounded text-xs">↑↓</kbd>
                    <kbd class="px-1.5 py-0.5 bg-gray-100 border border-gray-300 rounded text-xs ml-1">Enter</kbd>
                </span>
            </div>
        </div>

        {{-- Liste des options avec scroll --}}
        <div class="overflow-y-auto py-1" style="max-height: 280px;">
            @forelse($this->filteredOptions as $index => $option)
                <div
                    wire:key="option-{{ $option['value'] }}-{{ $loop->index }}"
                    data-option-index="{{ $loop->index }}"
                    data-option-value="{{ $option['value'] }}"
                    wire:click="selectOption('{{ $option['value'] }}')"
                    @click="open = false; focusedIndex = -1"
                    @mouseenter="focusedIndex = {{ $loop->index }}"
                    @keydown.enter.prevent="$el.click()"
                    @keydown.space.prevent="$el.click()"
                    role="option"
                    tabindex="0"
                    aria-selected="{{ $option['value'] == $value ? 'true' : 'false' }}"
                    class="cursor-pointer select-none relative px-3 py-2.5 transition-colors duration-100 
                           {{ $option['value'] == $value ? 'bg-indigo-600 text-white' : 'text-gray-900 hover:bg-indigo-50' }}"
                    :class="{ 'bg-indigo-100 ring-2 ring-inset ring-indigo-400': focusedIndex === {{ $loop->index }} && '{{ $option['value'] }}' != '{{ $value }}' }"
                >
                    <div class="flex items-center gap-2.5">
                        @if($option['value'] == $value)
                            <svg class="h-5 w-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        @else
                            <div class="h-5 w-5 flex-shrink-0 rounded-full border-2 border-gray-300"></div>
                        @endif
                        <span class="block truncate text-sm" :class="{ 'font-semibold': '{{ $option['value'] }}' == '{{ $value }}' }">
                            {{ $option['text'] ?? $option['label'] ?? '' }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="py-8 px-4 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="mt-2 text-sm text-gray-500 font-medium">{{ $noResultsText }}</p>
                    @if($search)
                        <p class="mt-1 text-xs text-gray-400">Essayez avec un autre terme</p>
                    @endif
                </div>
            @endforelse
        </div>
    </div>
</div>

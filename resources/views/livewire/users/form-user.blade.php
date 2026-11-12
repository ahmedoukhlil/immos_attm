<div>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    {{ $isEdit ? 'Modifier l\'utilisateur' : 'Créer un utilisateur' }}
                </h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $isEdit ? 'Modifiez les informations de l\'utilisateur' : 'Ajoutez un nouvel utilisateur au système' }}
                </p>
            </div>
            <a 
                href="{{ route('users.index') }}"
                class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Retour
            </a>
        </div>

        {{-- Messages d'erreur --}}
        @if ($errors->any())
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">
                            Des erreurs ont été détectées
                        </h3>
                        <div class="mt-2 text-sm text-red-700">
                            <ul class="list-disc list-inside space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Formulaire --}}
        <form wire:submit.prevent="save" class="space-y-6">
            <div 
                wire:loading.class="opacity-50 pointer-events-none"
                class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                
                {{-- Section 1 : Informations personnelles --}}
                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
                        Informations personnelles
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Nom d'utilisateur --}}
                        <div class="md:col-span-2">
                            <label for="users" class="block text-sm font-medium text-gray-700 mb-1">
                                Nom d'utilisateur <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text"
                                id="users"
                                wire:model="users"
                                placeholder="Ex: jdupont"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('users') border-red-300 @enderror"
                                wire:loading.attr="disabled">
                            @error('users')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">Nom d'utilisateur unique pour la connexion</p>
                        </div>
                    </div>
                </div>

                {{-- Section 2 : Authentification --}}
                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
                        Authentification
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Mot de passe --}}
                        <div>
                            <label for="mdp" class="block text-sm font-medium text-gray-700 mb-1">
                                Mot de passe @if(!$isEdit)<span class="text-red-500">*</span>@else<span class="text-gray-400">(laisser vide pour ne pas modifier)</span>@endif
                            </label>
                            <input 
                                type="password"
                                id="mdp"
                                wire:model="mdp"
                                placeholder="Mot de passe"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('mdp') border-red-300 @enderror"
                                wire:loading.attr="disabled">
                            @error('mdp')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">8 caractères minimum, avec une majuscule, une minuscule et un chiffre.</p>
                        </div>

                        {{-- Confirmation mot de passe --}}
                        <div>
                            <label for="mdp_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                                Confirmer le mot de passe @if(!$isEdit)<span class="text-red-500">*</span>@endif
                            </label>
                            <input 
                                type="password"
                                id="mdp_confirmation"
                                wire:model="mdp_confirmation"
                                placeholder="Répétez le mot de passe"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('mdp_confirmation') border-red-300 @enderror"
                                wire:loading.attr="disabled">
                            @error('mdp_confirmation')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Section 3 : Rôle et statut --}}
                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
                        Rôle et statut
                    </h2>
                    
                    <div class="grid grid-cols-1 gap-6">
                        {{-- Rôle --}}
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700 mb-1">
                                Rôle <span class="text-red-500">*</span>
                            </label>
                            <livewire:components.searchable-select
                                wire:model="role"
                                :options="$this->roleOptions"
                                placeholder="Sélectionner un rôle"
                                search-placeholder="Rechercher un rôle..."
                                no-results-text="Aucun rôle trouvé"
                                :allow-clear="false"
                                name="role"
                            />
                            @error('role')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">
                                <strong>Agent :</strong> Réalise les comptages physiques qui lui sont assignés dans la PWA.<br>
                                <strong>Gestionnaire :</strong> Gère toutes les opérations sauf les utilisateurs.<br>
                                <strong>Administrateur :</strong> Accès complet, y compris la gestion des utilisateurs.
                            </p>
                        </div>

                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                    <button 
                        type="button"
                        wire:click="cancel"
                        class="px-6 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                        Annuler
                    </button>
                    <button 
                        type="submit"
                        wire:loading.attr="disabled"
                        class="px-6 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="save">
                            {{ $isEdit ? 'Enregistrer les modifications' : 'Créer l\'utilisateur' }}
                        </span>
                        <span wire:loading wire:target="save">
                            Enregistrement...
                        </span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>


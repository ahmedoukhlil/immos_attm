<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- En-tête -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Gestion des rôles</h1>
                    <p class="text-gray-500 mt-1">Attribuer les rôles administrateur et agent aux utilisateurs</p>
                </div>
                <a href="{{ route('users.index') }}" 
                   class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Retour aux utilisateurs
                </a>
            </div>
        </div>

        <!-- Messages Flash -->
        @if (session()->has('success'))
            <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                {{ session('error') }}
            </div>
        @endif

        <!-- Statistiques -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total utilisateurs</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $stats['total'] }}</p>
                    </div>
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600"><x-icon name="users" class="w-6 h-6" /></div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Administrateurs</p>
                        <p class="text-3xl font-bold text-purple-600 mt-2">{{ $stats['admins'] }}</p>
                        <p class="text-xs text-gray-500 mt-1">Accès complet</p>
                    </div>
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-purple-50 text-purple-600"><x-icon name="shield-check" class="w-6 h-6" /></div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Agents</p>
                        <p class="text-3xl font-bold text-blue-600 mt-2">{{ $stats['agents'] }}</p>
                        <p class="text-xs text-gray-500 mt-1">Inventaires assignés</p>
                    </div>
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600"><x-icon name="user" class="w-6 h-6" /></div>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Recherche</label>
                    <div class="relative">
                        <input type="text" 
                               wire:model.live.debounce.300ms="search" 
                               placeholder="Rechercher un utilisateur..."
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <svg class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Filtrer par rôle</label>
                    <select wire:model.live="filterRole" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <option value="all">Tous les rôles</option>
                        <option value="admin">Administrateurs uniquement</option>
                        <option value="agent">Agents uniquement</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Légende des permissions -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-blue-900"><x-icon name="information-circle" class="w-4 h-4" /> Permissions par rôle</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="mb-2 flex items-center gap-1.5 font-semibold text-purple-700"><x-icon name="shield-check" class="w-4 h-4" /> Administrateur</p>
                    <ul class="text-gray-700 space-y-1">
                        <li class="flex items-center gap-2"><x-icon name="check-circle" class="w-4 h-4 text-green-600" /> Gestion des immobilisations, transferts et étiquettes</li>
                        <li class="flex items-center gap-2"><x-icon name="check-circle" class="w-4 h-4 text-green-600" /> Référentiel : localisations, sites, catégories, désignations</li>
                        <li class="flex items-center gap-2"><x-icon name="check-circle" class="w-4 h-4 text-green-600" /> Préparation, suivi et clôture des inventaires</li>
                        <li class="flex items-center gap-2"><x-icon name="check-circle" class="w-4 h-4 text-green-600" /> Gestion des utilisateurs</li>
                    </ul>
                </div>
                <div>
                    <p class="mb-2 flex items-center gap-1.5 font-semibold text-blue-700"><x-icon name="user" class="w-4 h-4" /> Agent</p>
                    <ul class="text-gray-700 space-y-1">
                        <li class="flex items-center gap-2"><x-icon name="check-circle" class="w-4 h-4 text-green-600" /> Scan des sites assignés avec l'application mobile</li>
                        <li class="flex items-center gap-2"><x-icon name="check-circle" class="w-4 h-4 text-green-600" /> Consultation des immobilisations, localisations et inventaires</li>
                        <li class="flex items-center gap-2"><x-icon name="x-circle" class="w-4 h-4 text-red-500" /> Modification du référentiel et des immobilisations</li>
                        <li class="flex items-center gap-2"><x-icon name="x-circle" class="w-4 h-4 text-red-500" /> Gestion des utilisateurs</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Liste des utilisateurs -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Utilisateur</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rôle actuel</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Permissions</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($users as $user)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                        <span class="text-indigo-600 font-semibold">{{ strtoupper(substr($user->users ?? 'U', 0, 1)) }}</span>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $user->users ?? 'N/A' }}</div>
                                        <div class="text-xs text-gray-500">ID: {{ $user->idUser }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($user->role === 'admin')
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800 border border-purple-200">
                                        <x-icon name="shield-check" class="w-3.5 h-3.5 mr-1 self-center" /> Administrateur
                                    </span>
                                @elseif($user->role === 'agent')
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 border border-blue-200">
                                        <x-icon name="user" class="w-3.5 h-3.5 mr-1 self-center" /> Agent
                                    </span>
                                @else
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 border border-gray-200">
                                        <x-icon name="question-mark-circle" class="w-3.5 h-3.5 mr-1 self-center" /> Non défini
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="text-xs text-gray-600">
                                    @if($user->role === 'admin')
                                        <span class="inline-flex items-center gap-1 text-green-600"><x-icon name="check-circle" class="w-3.5 h-3.5" /> Accès complet</span>
                                    @elseif($user->role === 'agent')
                                        <span class="inline-flex items-center gap-1 text-blue-600"><x-icon name="check-circle" class="w-3.5 h-3.5" /> Inventaires assignés</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-red-600"><x-icon name="x-circle" class="w-3.5 h-3.5" /> Aucun accès</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                @if($user->idUser === auth()->user()->idUser)
                                    <span class="text-gray-400 italic">Vous</span>
                                @else
                                    <div class="flex flex-col gap-1">
                                        @if($user->role !== 'admin')
                                            <button 
                                                wire:click="confirmRoleChange({{ $user->idUser }}, '{{ $user->role }}', 'admin')"
                                                class="inline-flex items-center gap-1 text-purple-600 hover:text-purple-900 font-medium text-xs">
                                                <x-icon name="shield-check" class="w-3.5 h-3.5" /> Admin
                                            </button>
                                        @endif
                                        @if($user->role !== 'agent')
                                            <button 
                                                wire:click="confirmRoleChange({{ $user->idUser }}, '{{ $user->role }}', 'agent')"
                                                class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-900 font-medium text-xs">
                                                <x-icon name="user" class="w-3.5 h-3.5" /> Agent
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <x-icon name="users" class="w-12 h-12 mb-3 text-gray-300" />
                                    <p class="text-sm font-medium text-gray-500">Aucun utilisateur trouvé</p>
                                    @if($search || $filterRole !== 'all')
                                        <p class="text-xs text-gray-400 mt-1">Essayez de modifier vos filtres</p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-6">
            {{ $users->links() }}
        </div>

        <!-- Modal de confirmation -->
        @if($confirmingRoleChange)
            <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cancelRoleChange"></div>

                    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                        Confirmer le changement de rôle
                                    </h3>
                                    <div class="mt-2">
                                        <p class="text-sm text-gray-500">
                                            Êtes-vous sûr de vouloir changer le rôle de cet utilisateur en 
                                            <strong class="text-indigo-600">
                                                @if($newRole === 'admin')
                                                    Administrateur
                                                @else
                                                    Agent
                                                @endif
                                            </strong> ?
                                        </p>
                                        <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                            <p class="text-xs text-yellow-800">
                                                <strong class="inline-flex items-center gap-1"><x-icon name="exclamation" class="w-3.5 h-3.5" /> Attention :</strong> Ce changement affectera immédiatement les permissions de l'utilisateur.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button 
                                wire:click="changeRole" 
                                type="button" 
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Confirmer
                            </button>
                            <button 
                                wire:click="cancelRoleChange" 
                                type="button" 
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Annuler
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

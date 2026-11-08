<x-guest-layout>
    <x-slot name="logo">
        <div class="flex flex-col items-center mb-8 animate-fade-in">
            <div class="mb-2 flex h-20 w-20 items-center justify-center rounded-2xl bg-indigo-700 text-white shadow-lg" aria-hidden="true">
                <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
            <p class="text-lg font-bold text-gray-900">Gestion des immobilisations</p>
        </div>
    </x-slot>

    <!-- Session Status -->
    @if (session('status'))
        <div class="mb-6 p-4 bg-green-100 border-l-4 border-green-600 rounded-r-lg flex items-start space-x-3 animate-slide-in shadow-md">
            <svg class="w-5 h-5 text-green-700 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <p class="text-sm font-bold text-green-900">{{ session('status') }}</p>
        </div>
    @endif

    <!-- Validation Errors -->
    @if ($errors->any())
        <div class="mb-6 p-4 bg-red-100 border-l-4 border-red-600 rounded-r-lg animate-slide-in shadow-md">
            <div class="flex items-start space-x-3">
                <svg class="w-5 h-5 text-red-700 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                <div class="flex-1">
                    <p class="text-sm font-bold text-red-900 mb-2">
                        {{ __('Oups ! Quelque chose s\'est mal passé.') }}
                    </p>
                    <ul class="space-y-1 text-sm text-red-800 font-medium">
                        @foreach ($errors->all() as $error)
                            <li class="flex items-start">
                                <span class="mr-2 font-bold">•</span>
                                <span>{{ $error }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="mb-6 p-4 bg-red-100 border-l-4 border-red-600 rounded-r-lg flex items-start space-x-3 animate-slide-in shadow-md">
            <svg class="w-5 h-5 text-red-700 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>
            <p class="text-sm font-bold text-red-900">{{ session('error') }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5" x-data="{ showPassword: false, isLoading: false }" @submit="isLoading = true">
        @csrf

        <!-- Nom d'utilisateur -->
        <div class="space-y-2">
            <label for="users" class="block text-sm font-bold text-gray-800">
                {{ __('Nom d\'utilisateur') }}
            </label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <input 
                    id="users" 
                    class="block w-full pl-10 pr-4 py-3 border-2 border-gray-300 rounded-lg shadow-sm bg-white placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600 transition-all duration-200" 
                    type="text" 
                    name="users" 
                    value="{{ old('users') }}" 
                    required 
                    autofocus 
                    autocomplete="username"
                    placeholder="Nom d'utilisateur">
            </div>
        </div>

        <!-- Password -->
        <div class="space-y-2">
            <label for="mdp" class="block text-sm font-bold text-gray-800">
                {{ __('Mot de passe') }}
            </label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <input 
                    id="mdp" 
                    class="block w-full pl-10 pr-12 py-3 border-2 border-gray-300 rounded-lg shadow-sm bg-white placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600 transition-all duration-200" 
                    :type="showPassword ? 'text' : 'password'"
                    name="mdp" 
                    required 
                    autocomplete="current-password"
                    placeholder="••••••••">
                <button 
                    type="button"
                    @click="showPassword = !showPassword"
                    :aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                    :aria-pressed="showPassword"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-indigo-600 hover:text-indigo-800 focus:outline-none transition-colors">
                    <svg x-show="!showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg x-show="showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <input 
                    id="remember_me" 
                    type="checkbox" 
                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-600 border-2 border-gray-400 rounded transition-colors" 
                    name="remember">
                <label for="remember_me" class="ml-2 text-sm font-medium text-gray-800 cursor-pointer select-none">
                    {{ __('Se souvenir de moi') }}
                </label>
            </div>
            {{-- Lien vers réinitialisation de mot de passe (désactivé pour l'instant) --}}
            {{-- @if (Route::has('password.request'))
                <a class="text-sm text-indigo-600 hover:text-indigo-800 font-medium transition-colors" href="{{ route('password.request') }}">
                    {{ __('Mot de passe oublié ?') }}
                </a>
            @endif --}}
        </div>

        <div>
            <button 
                type="submit" 
                :disabled="isLoading"
                style="background: linear-gradient(135deg, #383f7b 0%, #2d3366 50%, #222850 100%);"
                class="w-full flex justify-center items-center py-4 px-6 border-0 rounded-xl shadow-2xl text-base font-bold text-white hover:opacity-90 focus:outline-none focus:ring-4 focus:ring-indigo-300 focus:ring-offset-2 transition-all duration-200 transform hover:scale-[1.02] hover:shadow-3xl active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none">
                <svg x-show="isLoading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" style="display: none;">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <svg x-show="!isLoading" class="w-5 h-5 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                </svg>
                <span class="text-white" x-text="isLoading ? 'Connexion...' : '{{ __('Se connecter') }}'"></span>
            </button>
        </div>
    </form>

    <style>
        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slide-in {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .animate-fade-in {
            animation: fade-in 0.5s ease-out;
        }

        .animate-slide-in {
            animation: slide-in 0.3s ease-out;
        }
    </style>
</x-guest-layout>

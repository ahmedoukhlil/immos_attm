<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CanManageInventaire
{
    /**
     * Handle an incoming request.
     *
     * Vérifie que l'utilisateur est authentifié et a le rôle admin ou agent.
     * Redirige avec un message d'erreur si l'accès est refusé.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Vérifier que l'utilisateur est authentifié
        if (! Auth::check()) {
            return redirect()->route('login')
                ->with('error', 'Vous devez être connecté pour accéder à cette page.');
        }

        $user = Auth::user();

        if (! $user->canPerform('inventaires.view')) {
            return redirect()->route('dashboard')
                ->with('error', 'Accès non autorisé');
        }

        return $next($request);
    }
}

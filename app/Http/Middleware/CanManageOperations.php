<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanManageOperations
{
    /**
     * Autorise les administrateurs et gestionnaires, mais pas les agents.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->canManageOperations()) {
            return redirect()->route('dashboard')
                ->with('error', 'Cette action est réservée aux administrateurs et gestionnaires.');
        }

        return $next($request);
    }
}

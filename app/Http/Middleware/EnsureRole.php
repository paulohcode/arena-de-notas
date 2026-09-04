<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Acesso não autorizado.');
        }

        // Regra nº 1: administrador opera tudo no sistema.
        if ($user->isAdmin() || $user->role === $role) {
            return $next($request);
        }

        return redirect()->to($user->homeRoute())
            ->withErrors(['role' => 'Este acesso é de outro perfil.']);
    }
}

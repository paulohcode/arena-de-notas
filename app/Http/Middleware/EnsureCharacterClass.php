<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCharacterClass
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && $user->isStudent()
            && blank($user->character_class)
            && ! $request->routeIs('student.character.*', 'logout', 'password.edit', 'password.update')
        ) {
            return redirect()->route('student.character.edit');
        }

        return $next($request);
    }
}

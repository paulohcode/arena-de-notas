<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordStudentAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->user()?->recordAccess();

        return $next($request);
    }
}

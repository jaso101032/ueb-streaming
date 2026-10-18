<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOperatorPermission
{
    public function handle(Request $request, Closure $next, string $action): Response
    {
        abort_unless($request->user()?->allowsOperatorAction($action), 403);

        return $next($request);
    }
}

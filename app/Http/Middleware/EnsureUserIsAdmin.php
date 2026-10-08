<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Reject any request whose authenticated user is not an admin.
     *
     * Always stack this after 'auth' — it only decides role, not whether a
     * user is logged in at all.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== UserRole::Admin) {
            abort(403);
        }

        return $next($request);
    }
}

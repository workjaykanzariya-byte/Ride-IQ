<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserHasActiveMembership
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() || ! $request->user()->hasActiveMembership()) {
            return response()->json(['success' => false, 'message' => 'Active membership required.', 'errors' => []], 403);
        }

        return $next($request);
    }
}

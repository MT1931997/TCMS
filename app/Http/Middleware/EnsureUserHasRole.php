<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userRole = $request->user()?->type?->value;

        if (! in_array($userRole, $roles, true)) {
            return ApiResponse::error('ليس لديك صلاحية للوصول لهذه الصفحة.', 403);
        }

        return $next($request);
    }
}

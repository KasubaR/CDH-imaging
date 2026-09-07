<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $permissionEnum = Permission::tryFrom($permission);

        if ($permissionEnum === null || ! $user->hasPermission($permissionEnum)) {
            abort(403);
        }

        return $next($request);
    }
}

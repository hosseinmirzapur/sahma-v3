<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserManagementPermission
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response) $next
     * @param string ...$guards
     * @return Response
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        if (Auth::guard('web')->check()) {
            /** @var User $user */
            $user = $request->user();
            if (!($user->role->permission->full)) {
                abort(403, 'شما به این قسمت دسترسی ندارید.');
            }
        }
        return $next($request);
    }
}

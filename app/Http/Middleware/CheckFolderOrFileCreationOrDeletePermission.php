<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class CheckFolderOrFileCreationOrDeletePermission
{
  /**
   * Handle an incoming request.
   *
   * @param Request $request
   * @param Closure(Request): (Response) $next
   * @param string ...$guards
   * @return Response
   * @throws ValidationException
   */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        if (Auth::guard('web')->check()) {
            /** @var User $user */
            $user = $request->user();
            if ($user->role->permission->read_only) {
                throw ValidationException::withMessages(['message' => 'دسترسی لازم برای این قسمت را ندارید.']);
            }
        }
        return $next($request);
    }
}

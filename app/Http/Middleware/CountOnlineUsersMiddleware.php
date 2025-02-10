<?php

namespace App\Http\Middleware;

use App\Helper\TimeHelper;
use App\Models\Activity;
use App\Services\ActivityService;
use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class CountOnlineUsersMiddleware
{
  /**
   * Handle an incoming request.
   *
   * @param Closure(Request): (Response) $next
   * @throws BindingResolutionException
   */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->id) {
          // Check if a login activity has already been recorded for this session.
            if (!Session::has('login_activity_logged')) {
                $activityService = app()->make(ActivityService::class);
                $description = "کاربر $user->name وارد سیستم شد.";
                $activityService->logUserAction($user, Activity::TYPE_LOGIN, $user, $description);
                Session::put('login_activity_logged', true);
            }
        }

        return $next($request);
    }
}

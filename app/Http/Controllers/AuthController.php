<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use App\Services\ActivityService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Inertia\ResponseFactory;

class AuthController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('web.user.cartable.inbox.list');
    }

  /**
   * @return Response|ResponseFactory
   */
    public function loginPage(): Response|ResponseFactory
    {
        return inertia('Auth/Login');
    }

  /**
   * @throws ValidationException
   * @throws Exception
   */
    public function loginAction(Request $request): RedirectResponse
    {
        $request->validate([
        'username' => 'required|string',
        'password' => 'required|string|max:72'
        ]);
        $userName = strval($request->input('username'));
        $password = strval($request->input('password'));

        $user = User::query()->where('personal_id', $userName)->first();
        if (is_null($user)) {
            throw ValidationException::withMessages(['message' => 'کاربری با این شناسه وجود ندارد.']);
        }
        if ($user->deleted_at != null) {
            throw ValidationException::withMessages(['message' => 'مجوز ورود به سیستم را ندارید.']);
        }
        if (Hash::check($password, strval($user->password))) {
            if (!str_starts_with(strval($user->password), '$argon2id$')) {
                $user->password = Hash::make($password);
                $user->save();
            }
            $activityService = app()->make(ActivityService::class);
            $description = "کاربر {$user->name} وارد سیستم شد.";
            $activityService->logUserAction($user, Activity::TYPE_LOGIN, $user, $description);
            Auth::guard('web')->login($user);
            return redirect()->route('web.user.cartable.inbox.list');
        } else {
            throw ValidationException::withMessages(['message' => 'رمز عبور اشتباه است.']);
        }
    }

    public function logout(Request $request, ActivityService $activityService): RedirectResponse
    {
      /* @var User $user */
        $user = $request->user();
        Redis::connection('cache')->hset('logged-out-users-today', strval($user->id), strval(now()->timestamp));

        $description = "کاربر {$user->name} از سیستم خارج شد.";
      /** @phpstan-ignore-next-line */
        $activityService->logUserAction($user, Activity::TYPE_LOGOUT, $user, $description);

        Auth::guard('web')->logout();
        return redirect()->route('web.user.login-page');
    }
}

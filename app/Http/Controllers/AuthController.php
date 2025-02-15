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
    public function __construct(private readonly ActivityService $activityService)
    {
    }

    public function index(): RedirectResponse
    {
        return redirect()->route('web.user.cartable.inbox.list');
    }

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
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string|max:72'
        ]);

        $user = User::query()
            ->where('personal_id', $validated['username'])
            ->first();

        abort_if(!$user, 422, 'کاربری با این شناسه وجود ندارد.');
        abort_if($user->deleted_at !== null, 403, 'مجوز ورود به سیستم را ندارید.');

        if (!Hash::check(strval($validated['password']), strval($user->password))) {
            throw ValidationException::withMessages(['message' => 'رمز عبور اشتباه است.']);
        }

        // Rehash password if needed (more secure than manually checking $argon2id)
        if (password_needs_rehash(strval($user->password), PASSWORD_ARGON2ID)) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        $this->activityService->logUserAction(
            $user,
            Activity::TYPE_LOGIN,
            $user,
            "کاربر $user->name وارد سیستم شد."
        );

        Auth::guard('web')->login($user);
        if (config('ui.has_cartable')) {
            return redirect()->route('web.user.cartable.inbox.list');
        }

        return redirect()->route('web.user.dashboard.index');
    }

    public function logout(Request $request, ActivityService $activityService): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        Redis::connection('cache')
            ->client()
            ->hset(
                'logged-out-users-today',
                (string)$user->id,
                (string)now()->timestamp
            );

        $activityService->logUserAction(
            $user,
            Activity::TYPE_LOGOUT,
            $user,
            "کاربر $user->name از سیستم خارج شد."
        );

        Auth::guard('web')->logout();
        return redirect()->route('web.user.login-page');
    }
}

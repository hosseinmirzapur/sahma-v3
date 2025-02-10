<?php

namespace App\Http\Middleware;

use App\Models\Letter;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermissionLetter
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /* @var Letter $letter */
        $letter = $request->route('letter');
        if (!$letter) {
            abort(404, 'این نامه وجود ندارد');
        }

        /* @var User $user */
        $user = $request->user();
        if (!$user) {
            abort(403, 'شما دسترسی لازم برای این بخش را ندارید');
        }

        $userOwnsLetter = $letter->user_id === $user->id;
        $userHasTheLetterInInbox = $letter->letterInboxes()
            ->where('user_id', $user->id)
            ->exists();

        abort_if(!$userOwnsLetter && !$userHasTheLetterInInbox, 403, 'نامه متعلق به شما نیست.');

        return $next($request);
    }
}

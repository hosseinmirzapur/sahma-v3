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
      /* @var Letter $letter*/
        $letter = $request->route('letter');

      /* @var User $user */
        $user = $request->user();

        if ($letter->user_id == $user->id || $letter->letterInboxes()->where('user_id', $user->id)->exists()) {
            return $next($request);
        } else {
            abort(403, 'نامه متعلق به شما نیست.');
        }
    }
}

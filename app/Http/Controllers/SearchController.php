<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SearchController extends Controller
{
    public function listUsers(Request $request): JsonResponse
    {
        $identifier = $request->input('identifier', '');

        $users = User::query()
            ->where('name', 'LIKE', "%$identifier%")
            ->whereNull('deleted_at')
            ->select(['id', 'name', 'personal_id'])
            ->get()
            ->map(fn(User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'personalId' => $user->personal_id
            ]);

        return response()->json($users);
    }

    /**
     * @throws ValidationException
     */
    public function listLetters(Request $request): JsonResponse
    {
        /* @var User $user */
        $user = $request->user();

        $identifier = $request->input('identifier');
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        if (!$identifier && !$startDate) {
            throw ValidationException::withMessages([
                'message' => 'برای جسنجو نامه حداقل به شماره نامه یا بازه تاریخ ارسال نامه نیاز می‌باشد.'
            ]);
        }

        $lettersQuery = Letter::query()
            ->select('letters.id', 'letters.subject', 'letters.category')
            ->leftJoin(
                'letter_inboxes',
                'letter_inboxes.letter_id',
                '=',
                'letters.id'
            )
            ->where(fn($query) => $query->where('letters.user_id', $user->id)
                ->orWhere('letter_inboxes.user_id', $user->id))
            ->when($identifier, fn($query) => $query->where('letters.id', 'LIKE', "$identifier%"))
            ->when($startDate, function ($query) use ($startDate, $endDate) {
                if (!$endDate) {
                    throw ValidationException::withMessages([
                        'message' => 'بازه تاریخی باشد شامل ابتدا و انتها باشد'
                    ]);
                }
                $query->whereBetween('submitted_at', [Carbon::parse($startDate), Carbon::parse($endDate)]);
            });

        return response()->json($lettersQuery->distinct('letters.id')->get());
    }
}

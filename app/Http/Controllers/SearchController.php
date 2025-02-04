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
        $identifier = strval($request->input('identifier'));
        $users = User::query()
        ->where('name', 'LIKE', "%$identifier%")
        ->whereNull('deleted_at')
        ->get()
        ->map(function (User $user) {
            return [
            'id' => $user->id,
            'name' => $user->name,
            'personalId' => $user->personal_id
            ];
        });
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

        if (is_null($identifier) && is_null($startDate)) {
            throw ValidationException::withMessages(
                ['message' => 'برای جسنجو نامه حداقل به شماره نامه یا بازه تاریخ ارسال نامه نیاز می‌باشد.']
            );
        }

        $lettersQuery = Letter::query()
        ->select('letters.*')
        ->leftJoin('letter_inboxes', 'letter_inboxes.letter_id', '=', 'letters.id')
        ->where('letters.user_id', $user->id)
        ->orWhere('letter_inboxes.user_id', $user->id);

        if ($identifier) {
            $lettersQuery = $lettersQuery->where('letters.id', 'LIKE', "$identifier%");
        } elseif ($startDate) {
            if (is_null($endDate)) {
                throw ValidationException::withMessages(
                    ['message' => 'بازه تاریخی باشد شامل ابتدا و انتها باشد']
                );
            }
            $startDate = Carbon::parse(strval($startDate));
            $endDate = Carbon::parse(strval($endDate));
            $lettersQuery = $lettersQuery->whereBetween('submitted_at', [date($startDate), date($endDate)]);
        }

        $letters = $lettersQuery
        ->distinct('letters.id')
        ->get()
        ->map(function (Letter $letter) {
            return [
            'id' => $letter->id,
            'subject' => $letter->subject,
            'category' => $letter->category
            ];
        });
        return response()->json($letters);
    }
}

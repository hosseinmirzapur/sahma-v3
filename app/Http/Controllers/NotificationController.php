<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Inertia\Response;
use Inertia\ResponseFactory;

class NotificationController extends Controller
{
    public function index(Request $request): Response|ResponseFactory
    {
      /* @var User $user */
        $user = $request->user();

        $notifications = $user->notifications()->get()->map(function (Notification $notification) {
            return [
            'letterId' => $notification->letter ? $notification->letter->id : null,
            'subject' => $notification->subject,
            'description' => $notification->description,
            'remindAt' => $notification->remind_at,
            'priority' => $notification->priority
            ];
        })->toArray();

        return inertia('Dashboard/Reminders/Reminder', [
        'notifications' => $notifications
        ]);
    }

    public function createAction(Request $request)
    {
        /* @var User $user*/
        $user = $request->user();
        $request->validate([
        'letter_id' => 'nullable|integer|exists:letters,id',
        'subject' => 'required|string',
        'remindAt' => 'required|string',
        'description' => 'required|string',
        'priority' => 'required|string|in:' . implode(',', Letter::getAllLetterPriorities())
        ]);
        $remindAt = $request->input('remindAt');
        if (!is_null($remindAt)) {
            $remindAt = Carbon::parse(strval($remindAt));
        }
        $letterId = $request->input('letter_id');
        $letter = null;
        if (!is_null($letterId)) {
            $letter = Letter::find($letterId);
        }

        $subject = $request->input('subject');
        $description = $request->input('description');
        $priority = $request->input('priority');

        Notification::query()->create([
        'letter_id' => $letter ? $letter->id : null,
        'user_id' => $user->id,
        'subject' => $subject,
        'remind_at' => $remindAt,
        'description' => $description,
        'priority' => $priority
        ]);

        return redirect()->back();
    }
}

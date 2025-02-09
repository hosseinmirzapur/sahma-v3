<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Response;
use Inertia\ResponseFactory;

class NotificationController extends Controller
{
    /**
     * @param Request $request
     * @return Response|ResponseFactory
     */
    public function index(Request $request): Response|ResponseFactory
    {
        /* @var User $user */
        $user = $request->user();
        if (!$user) {
            abort(403, 'شما اجازه دسترسی به این قسمت را ندارید');
        }

        $notifications = $user->notifications()
            ->get()
            ->map(function (Notification $notification) {
                return [
                    'letterId' => $notification->letter->id ?? null,
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

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function createAction(Request $request): RedirectResponse
    {
        /* @var User $user */
        $user = $request->user();

        $validatedData = $request->validate([
            'letter_id' => 'nullable|integer|exists:letters,id',
            'subject' => 'required|string',
            'remindAt' => 'required|string',
            'description' => 'required|string',
            'priority' => 'required|string|in:' . implode(',', Letter::getAllLetterPriorities()),
        ]);

        $remindAt = Carbon::parse($validatedData['remindAt']);
        $letterId = $validatedData['letter_id'] ?? null;
        $letterId = Letter::query()->find($letterId)->id ?? null;

        Notification::query()->create([
            'letter_id' => $letterId,
            'user_id' => $user->id,
            'subject' => $validatedData['subject'],
            'remind_at' => $remindAt,
            'description' => $validatedData['description'],
            'priority' => $validatedData['priority'],
        ]);

        return redirect()->back();
    }
}

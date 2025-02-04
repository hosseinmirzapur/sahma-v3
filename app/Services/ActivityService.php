<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\EntityGroup;
use App\Models\Folder;
use App\Models\User;

class ActivityService
{
    public function logUserAction(
        User $user,
        string $status,
        User|Folder|EntityGroup $activityModel,
        string $description
    ): void {
        Activity::query()->create([
            'user_id' => $user->id,
            'status' => $status,
            'description' => $description,
            'activity_type' => get_class($activityModel),
            'activity_id' => $activityModel->id,
        ]);
    }

    public static function getActivityByType(User|Folder|EntityGroup $activityModel): array
    {
        return Activity::query()
            ->select(['id', 'description', 'created_at'])
            ->where('activity_type', get_class($activityModel))
            ->where('activity_id', $activityModel->id)
            ->get()
            ->map(fn(Activity $activity) => [
                'id' => $activity->id,
                'description' => $activity->description,
                'created_at' => timestamp_to_persian_datetime($activity->created_at)
            ])
            ->toArray();
    }
}

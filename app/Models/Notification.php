<?php

namespace App\Models;

use Barryvdh\LaravelIdeHelper\Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * App\Models\Notification
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $letter_id
 * @property string|null $subject
 * @property string|null $description
 * @property string $priority
 * @property array|null $meta
 * @property string $remind_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Letter|null $letter
 * @property-read User $user
 * @method static Builder|Notification newModelQuery()
 * @method static Builder|Notification newQuery()
 * @method static Builder|Notification query()
 * @method static Builder|Notification whereCreatedAt($value)
 * @method static Builder|Notification whereDescription($value)
 * @method static Builder|Notification whereId($value)
 * @method static Builder|Notification whereLetterId($value)
 * @method static Builder|Notification whereMeta($value)
 * @method static Builder|Notification wherePriority($value)
 * @method static Builder|Notification whereRemindAt($value)
 * @method static Builder|Notification whereSubject($value)
 * @method static Builder|Notification whereUpdatedAt($value)
 * @method static Builder|Notification whereUserId($value)
 * @mixin \Eloquent
 */
class Notification extends Model
{
    use HasFactory;

    public const PRIORITY_NORMAL = 'NORMAL'; //معمولی
    public const PRIORITY_IMMEDIATELY = 'IMMEDIATELY'; //فوری
    public const PRIORITY_INSTANT = 'INSTANT'; //آنی


    protected $fillable = [
    'letter_id', 'user_id', 'type', 'description', 'priority', 'remind_at'
    ];

    protected $casts = [
    'meta' => 'json',
    ];


  /**
   * @return BelongsTo
   */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

  /**
   * @return BelongsTo
   */
    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

  /**
   * @throws ValidationException
   */
    public function getPriorityNotification(): string
    {
        return match ($this->priority) {
            Notification::PRIORITY_NORMAL => 'عادی',
            Notification::PRIORITY_IMMEDIATELY => 'فوری',
            Notification::PRIORITY_INSTANT => 'آنی',
            default => throw ValidationException::withMessages(['message' => 'unsupported priority notification'])
        };
    }
}

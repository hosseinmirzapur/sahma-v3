<?php

namespace App\Models;

use Barryvdh\LaravelIdeHelper\Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * App\Models\Letter
 *
 * @property int $id
 * @property int $user_id
 * @property string $subject
 * @property string|null $text
 * @property string $status
 * @property string|null $description
 * @property array|null $meta
 * @property string $priority
 * @property string|null $submitted_at
 * @property string|null $due_date
 * @property string $category
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $letter_reference_type
 * @property int|null $letter_reference_id
 * @property-read Collection<int, \App\Models\Activity> $activity
 * @property-read int|null $activity_count
 * @property-read Collection<int, \App\Models\LetterAttachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read Collection<int, \App\Models\LetterInbox> $letterInboxes
 * @property-read int|null $letter_inboxes_count
 * @property-read Collection<int, \App\Models\LetterReply> $letterReplies
 * @property-read int|null $letter_replies_count
 * @property-read Collection<int, \App\Models\LetterSign> $letterSigns
 * @property-read int|null $letter_signs_count
 * @property-read Collection<int, \App\Models\Notification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \App\Models\User $user
 * @method static Builder|Letter newModelQuery()
 * @method static Builder|Letter newQuery()
 * @method static Builder|Letter query()
 * @method static Builder|Letter whereCategory($value)
 * @method static Builder|Letter whereCreatedAt($value)
 * @method static Builder|Letter whereDescription($value)
 * @method static Builder|Letter whereDueDate($value)
 * @method static Builder|Letter whereId($value)
 * @method static Builder|Letter whereLetterReferenceId($value)
 * @method static Builder|Letter whereLetterReferenceType($value)
 * @method static Builder|Letter whereMeta($value)
 * @method static Builder|Letter wherePriority($value)
 * @method static Builder|Letter whereStatus($value)
 * @method static Builder|Letter whereSubject($value)
 * @method static Builder|Letter whereSubmittedAt($value)
 * @method static Builder|Letter whereText($value)
 * @method static Builder|Letter whereUpdatedAt($value)
 * @method static Builder|Letter whereUserId($value)
 * @mixin \Eloquent
 */
class Letter extends Model
{
    use HasFactory;

    public const PRIORITY_NORMAL = 'NORMAL'; //معمولی
    public const PRIORITY_IMMEDIATELY = 'IMMEDIATELY'; //فوری
    public const PRIORITY_INSTANT = 'INSTANT'; //آنی
    public const CATEGORY_NORMAL = 'NORMAL'; //معمولی
    public const CATEGORY_SECRET = 'SECRET'; //سری
    public const CATEGORY_CONFIDENTIAL = 'CONFIDENTIAL'; //محرمانه
    public const STATUS_SENT = 'SENT';
    public const STATUS_RECEIVED = 'RECEIVED';
    public const STATUS_REPLIED = 'REPLIED';
    public const STATUS_ACHIEVED = 'ACHIEVED';
    public const STATUS_DELETED = 'DELETED';
    public const STATUS_DRAFT = 'DRAFT';

    protected $fillable = [
        'user_id', 'subject', 'read_status', 'text', 'status', 'meta', 'description',
        'priority', 'submitted_at', 'due_date', 'category', 'letter_reference_type', 'letter_reference_id'
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

    public function activity(): MorphMany
    {
        return $this->morphMany(Activity::class, 'activity');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(LetterAttachment::class, 'attachable');
    }

    /**
     * @return HasMany
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * @return HasMany
     */
    public function letterSigns(): HasMany
    {
        return $this->hasMany(LetterSign::class);
    }

    /**
     * @return HasMany
     */
    public function letterInboxes(): HasMany
    {
        return $this->hasMany(LetterInbox::class);
    }

    /**
     * @return HasMany
     */
    public function letterReplies(): HasMany
    {
        return $this->hasMany(LetterReply::class);
    }

    public function getLetterStatus(User $user): string
    {
        $status = null;
        if ($this->status == Letter::STATUS_SENT) {
            if ($this->user_id == $user->id) {
                $status = Letter::STATUS_SENT;
            } else {
                $status = Letter::STATUS_RECEIVED;
            }
        } else {
            $status = $this->status;
        }
        return $status;
    }

    /**
     * @throws ValidationException
     */
    public function getPriorityLetterInPersian(): string
    {
        return match ($this->priority) {
            Letter::PRIORITY_NORMAL => 'عادی',
            Letter::PRIORITY_IMMEDIATELY => 'فوری',
            Letter::PRIORITY_INSTANT => 'آنی',
            default => throw ValidationException::withMessages(['message' => 'unsupported priority letter'])
        };
    }

    /**
     * @throws ValidationException
     */
    public function getCategoryLetterInPersian(): string
    {
        return match ($this->priority) {
            Letter::CATEGORY_SECRET => 'عادی',
            Letter::CATEGORY_CONFIDENTIAL => 'فوری',
            Letter::CATEGORY_NORMAL => 'آنی',
            default => throw ValidationException::withMessages(['message' => 'unsupported category letter'])
        };
    }

    public static function getAllLetterCategories(): array
    {
        return [
            self::CATEGORY_NORMAL,
            self::CATEGORY_CONFIDENTIAL,
            self::CATEGORY_SECRET
        ];
    }

    public static function getAllLetterPriorities(): array
    {
        return [
            self::PRIORITY_INSTANT,
            self::PRIORITY_IMMEDIATELY,
            self::PRIORITY_NORMAL
        ];
    }

    public static function getmimeTypes(): array
    {
        return ['jpeg', 'jpg', 'png', 'pdf'];
    }
}

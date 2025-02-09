<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\LetterInbox
 *
 * @property int $id
 * @property int|null $letter_id
 * @property int $user_id
 * @property string $read_status
 * @property string $is_refer
 * @property int|null $referred_by
 * @property string|null $refer_description
 * @property string|null $due_date
 * @property array|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Letter|null $letter
 * @property-read User|null $referrerUser
 * @property-read User $user
 * @method static Builder|LetterInbox newModelQuery()
 * @method static Builder|LetterInbox newQuery()
 * @method static Builder|LetterInbox query()
 * @method static Builder|LetterInbox whereCreatedAt($value)
 * @method static Builder|LetterInbox whereDueDate($value)
 * @method static Builder|LetterInbox whereId($value)
 * @method static Builder|LetterInbox whereIsRefer($value)
 * @method static Builder|LetterInbox whereLetterId($value)
 * @method static Builder|LetterInbox whereMeta($value)
 * @method static Builder|LetterInbox whereReadStatus($value)
 * @method static Builder|LetterInbox whereReferDescription($value)
 * @method static Builder|LetterInbox whereReferredBy($value)
 * @method static Builder|LetterInbox whereUpdatedAt($value)
 * @method static Builder|LetterInbox whereUserId($value)
 * @mixin Eloquent
 */
class LetterInbox extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'letter_id',
        'read_status',
        'text',
        'meta',
        'due_date',
        'is_refer',
        'referred_by',
        'refer_description'
    ];

    protected $casts = ['meta' => 'json'];

    /**
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo
     */
    public function referrerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    /**
     * @return BelongsTo
     */
    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }
}

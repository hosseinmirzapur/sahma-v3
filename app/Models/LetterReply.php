<?php

namespace App\Models;

use Barryvdh\LaravelIdeHelper\Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * App\Models\ReplyLetter
 *
 * @property int $id
 * @property int|null $letter_id
 * @property int $user_id
 * @property int|null $recipient_id
 * @property string $text
 * @property array|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Letter|null $letter
 * @property-read User $user
 * @method static Builder|letterReply newModelQuery()
 * @method static Builder|letterReply newQuery()
 * @method static Builder|letterReply query()
 * @method static Builder|letterReply whereCreatedAt($value)
 * @method static Builder|letterReply whereId($value)
 * @method static Builder|letterReply whereLetterId($value)
 * @method static Builder|letterReply whereMeta($value)
 * @method static Builder|letterReply whereRecipientId($value)
 * @method static Builder|letterReply whereText($value)
 * @method static Builder|letterReply whereUpdatedAt($value)
 * @method static Builder|letterReply whereUserId($value)
 * @mixin Eloquent
 */
class LetterReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'letter_id', 'text', 'meta', 'recipient_id'
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

    public function attachments(): MorphMany
    {
        return $this->morphMany(LetterAttachment::class, 'attachable');
    }
}

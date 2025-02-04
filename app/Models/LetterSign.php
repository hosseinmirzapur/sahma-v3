<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\LetterSign
 *
 * @property int $id
 * @property int|null $letter_id
 * @property int|null $user_id
 * @property string|null $signed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Letter|null $letter
 * @property-read User $user
 * @method static Builder|LetterSign newModelQuery()
 * @method static Builder|LetterSign newQuery()
 * @method static Builder|LetterSign query()
 * @method static Builder|LetterSign whereCreatedAt($value)
 * @method static Builder|LetterSign whereId($value)
 * @method static Builder|LetterSign whereLetterId($value)
 * @method static Builder|LetterSign whereSignedAt($value)
 * @method static Builder|LetterSign whereUpdatedAt($value)
 * @method static Builder|LetterSign whereUserId($value)
 * @mixin Eloquent
 */
class LetterSign extends Model
{
    use HasFactory;

    protected $fillable = [
    'letter_id', 'user_id', 'signed_at'
    ];

  /**
   * @return BelongsTo
   */
    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

  /**
   * @return BelongsTo
   */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

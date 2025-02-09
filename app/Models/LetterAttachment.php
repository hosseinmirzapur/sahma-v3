<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\LetterAttachment
 *
 * @property int $id
 * @property string $type
 * @property string $file_location
 * @property string $attachable_type
 * @property int $attachable_id
 * @property array|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $attachable
 * @property-read \App\Models\Letter|null $letter
 * @method static Builder|LetterAttachment newModelQuery()
 * @method static Builder|LetterAttachment newQuery()
 * @method static Builder|LetterAttachment query()
 * @method static Builder|LetterAttachment whereAttachableId($value)
 * @method static Builder|LetterAttachment whereAttachableType($value)
 * @method static Builder|LetterAttachment whereCreatedAt($value)
 * @method static Builder|LetterAttachment whereFileLocation($value)
 * @method static Builder|LetterAttachment whereId($value)
 * @method static Builder|LetterAttachment whereMeta($value)
 * @method static Builder|LetterAttachment whereType($value)
 * @method static Builder|LetterAttachment whereUpdatedAt($value)
 * @mixin Eloquent
 */
class LetterAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'file_location', 'meta'
    ];

    protected $casts = [
        'meta' => 'json',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}

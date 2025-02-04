<?php

namespace App\Models;

use Barryvdh\LaravelIdeHelper\Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\Activity
 *
 * @property int $id
 * @property int $user_id
 * @property string $status
 * @property string|null $description
 * @property string $activity_type
 * @property int $activity_id
 * @property array|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|\Eloquent $activity
 * @property-read User $user
 * @method static Builder|Activity forPeriod($start, $end)
 * @method static Builder|Activity logins()
 * @method static Builder|Activity logouts()
 * @method static Builder|Activity newModelQuery()
 * @method static Builder|Activity newQuery()
 * @method static Builder|Activity query()
 * @method static Builder|Activity whereActivityId($value)
 * @method static Builder|Activity whereActivityType($value)
 * @method static Builder|Activity whereCreatedAt($value)
 * @method static Builder|Activity whereDescription($value)
 * @method static Builder|Activity whereId($value)
 * @method static Builder|Activity whereMeta($value)
 * @method static Builder|Activity whereStatus($value)
 * @method static Builder|Activity whereUpdatedAt($value)
 * @method static Builder|Activity whereUserId($value)
 * @mixin \Eloquent
 */
class Activity extends Model
{
    use HasFactory;

    public const TYPE_CREATE = 'CREATE';
    public const TYPE_PRINT = 'PRINT';
    public const TYPE_DESCRIPTION = 'DESCRIPTION';
    public const TYPE_UPLOAD = 'UPLOAD';
    public const TYPE_DELETE = 'DELETE';
    public const TYPE_RENAME = 'RENAME';
    public const TYPE_COPY = 'COPY';
    public const TYPE_EDIT = 'EDIT';
    public const TYPE_TRANSCRIPTION = 'TRANSCRIPTION';
    public const TYPE_LOGIN = 'LOGIN';
    public const TYPE_LOGOUT = 'LOGOUT';
    public const TYPE_ARCHIVE = 'ARCHIVE';
    public const TYPE_RETRIEVAL = 'RETRIEVAL';
    public const TYPE_MOVE = 'MOVE';
    public const TYPE_DOWNLOAD = 'DOWNLOAD';

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
    protected $fillable = [
    'user_id',
    'type',
    'meta'
    ];
    protected $casts = [
    'meta' => 'json'
    ];

  /**
   * @return BelongsTo
   */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function activity(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForPeriod(Builder $query, string $start, string $end): Builder
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    public function scopeLogins(Builder $query): Builder
    {
        return $query->where('status', self::TYPE_LOGIN);
    }

    public function scopeLogouts(Builder $query): Builder
    {
        return $query->where('status', self::TYPE_LOGOUT);
    }
}

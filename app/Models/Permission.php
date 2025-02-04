<?php

namespace App\Models;

use Barryvdh\LaravelIdeHelper\Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * App\Models\Permission
 *
 * @property int $id
 * @property int $full
 * @property int $modify
 * @property int $read_only
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Builder|Permission newModelQuery()
 * @method static Builder|Permission newQuery()
 * @method static Builder|Permission query()
 * @method static Builder|Permission whereCreatedAt($value)
 * @method static Builder|Permission whereFull($value)
 * @method static Builder|Permission whereId($value)
 * @method static Builder|Permission whereModify($value)
 * @method static Builder|Permission whereReadOnly($value)
 * @method static Builder|Permission whereUpdatedAt($value)
 * @mixin Eloquent
 * @property int $role_id
 * @property-read Role $role
 * @method static Builder|Permission whereRoleId($value)
 * @mixin \Eloquent
 */
class Permission extends Model
{
    protected $fillable = ['role_id', 'full', 'modify', 'read_only'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}

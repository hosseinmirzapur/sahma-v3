<?php

namespace App\Models;

use Barryvdh\LaravelIdeHelper\Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\DepartmentUser
 *
 * @property int $id
 * @property int $user_id
 * @property int $department_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Department $department
 * @property-read User $user
 * @method static Builder|DepartmentUser newModelQuery()
 * @method static Builder|DepartmentUser newQuery()
 * @method static Builder|DepartmentUser query()
 * @method static Builder|DepartmentUser whereCreatedAt($value)
 * @method static Builder|DepartmentUser whereDepartmentId($value)
 * @method static Builder|DepartmentUser whereId($value)
 * @method static Builder|DepartmentUser whereUpdatedAt($value)
 * @method static Builder|DepartmentUser whereUserId($value)
 * @mixin Eloquent
 */
class DepartmentUser extends Model
{
    protected $fillable = ['user_id', 'department_id'];
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
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}

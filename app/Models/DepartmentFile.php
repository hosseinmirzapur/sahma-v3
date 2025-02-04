<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\DepartmentFile
 *
 * @property int $id
 * @property int $entity_group_id
 * @property int $department_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Department $department
 * @property-read EntityGroup $entityGroup
 * @method static Builder|DepartmentFile newModelQuery()
 * @method static Builder|DepartmentFile newQuery()
 * @method static Builder|DepartmentFile query()
 * @method static Builder|DepartmentFile whereCreatedAt($value)
 * @method static Builder|DepartmentFile whereDepartmentId($value)
 * @method static Builder|DepartmentFile whereEntityGroupId($value)
 * @method static Builder|DepartmentFile whereId($value)
 * @method static Builder|DepartmentFile whereUpdatedAt($value)
 * @mixin Eloquent
 */
class DepartmentFile extends Model
{
    protected $fillable = ['entity_group_id', 'department_id'];
    /**
     * @return BelongsTo
     */
    public function entityGroup(): BelongsTo
    {
        return $this->belongsTo(EntityGroup::class);
    }
    /**
     * @return BelongsTo
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}

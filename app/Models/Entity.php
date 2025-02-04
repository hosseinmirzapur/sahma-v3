<?php

namespace App\Models;

use Eloquent;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * App\Models\Entity
 *
 * @property int $id
 * @property int|null $entity_group_id
 * @property string $type
 * @property string|null $transcription_result
 * @property string $file_location
 * @property array|null $result_location
 * @property array|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EntityGroup|null $entityGroup
 * @method static Builder|Entity newModelQuery()
 * @method static Builder|Entity newQuery()
 * @method static Builder|Entity query()
 * @method static Builder|Entity whereCreatedAt($value)
 * @method static Builder|Entity whereEntityGroupId($value)
 * @method static Builder|Entity whereFileLocation($value)
 * @method static Builder|Entity whereId($value)
 * @method static Builder|Entity whereMeta($value)
 * @method static Builder|Entity whereResultLocation($value)
 * @method static Builder|Entity whereTranscriptionResult($value)
 * @method static Builder|Entity whereType($value)
 * @method static Builder|Entity whereUpdatedAt($value)
 * @mixin Eloquent
 */
class Entity extends Model
{
    use HasFactory;


    protected $fillable = [
    'entity_group_id', 'type', 'transcription_result', 'file_location',
    'result_location', 'meta'
    ];

    protected $casts = [
    'meta' => 'json',
    'result_location' => 'json'
    ];

  /**
   * @return BelongsTo
   */
    public function entityGroup(): BelongsTo
    {
        return $this->belongsTo(EntityGroup::class);
    }

  /**
   * @throws BindingResolutionException
   * @throws Exception
   */
    public function getFileData(): ?string
    {
        $data = Storage::disk($this->type)->get($this->file_location);
        if (empty($data)) {
            throw new Exception("Failed to get entity data. Entity id: #$this->id");
        }
        return $data;
    }


    public function getExtensionFile(): string
    {
        return strval(pathinfo($this->file_location, PATHINFO_EXTENSION));
    }

    public function fileNotExists(): bool
    {
        return !$this->fileExists();
    }

    public function fileExists(): bool
    {
        if ($this->file_location == null) {
            return false;
        }

        return Storage::disk($this->type)->exists($this->file_location);
    }
}

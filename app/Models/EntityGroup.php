<?php

namespace App\Models;

use App\Helper\EncryptHelper;
use App\Helper\TimeHelper;
use Barryvdh\LaravelIdeHelper\Eloquent;
use Database\Factories\EntityGroupFactory;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * App\Models\EntityGroup
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $parent_folder_id
 * @property string $name
 * @property string $type
 * @property string|null $transcription_result
 * @property string|null $transcription_at
 * @property string $status
 * @property array|null $meta
 * @property string $file_location
 * @property string|null $description
 * @property string|null $archived_at
 * @property array|null $result_location
 * @property int $number_of_try
 * @property string|null $deleted_at
 * @property string|null $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activity
 * @property-read int|null $activity_count
 * @property-read Collection<int, Entity> $entities
 * @property-read int|null $entities_count
 * @property-read Folder|null $parentFolder
 * @property-read User $user
 * @method static Builder|EntityGroup availableNow()
 * @method static EntityGroupFactory factory($count = null, $state = [])
 * @method static Builder|EntityGroup newModelQuery()
 * @method static Builder|EntityGroup newQuery()
 * @method static Builder|EntityGroup query()
 * @method static Builder|EntityGroup textSearch(string $searchText)
 * @method static Builder|EntityGroup whereArchivedAt($value)
 * @method static Builder|EntityGroup whereCreatedAt($value)
 * @method static Builder|EntityGroup whereDeletedAt($value)
 * @method static Builder|EntityGroup whereDescription($value)
 * @method static Builder|EntityGroup whereFileLocation($value)
 * @method static Builder|EntityGroup whereId($value)
 * @method static Builder|EntityGroup whereMeta($value)
 * @method static Builder|EntityGroup whereName($value)
 * @method static Builder|EntityGroup whereNumberOfTry($value)
 * @method static Builder|EntityGroup whereParentFolderId($value)
 * @method static Builder|EntityGroup whereResultLocation($value)
 * @method static Builder|EntityGroup whereSlug($value)
 * @method static Builder|EntityGroup whereStatus($value)
 * @method static Builder|EntityGroup whereTranscriptionAt($value)
 * @method static Builder|EntityGroup whereTranscriptionResult($value)
 * @method static Builder|EntityGroup whereType($value)
 * @method static Builder|EntityGroup whereUpdatedAt($value)
 * @method static Builder|EntityGroup whereUserId($value)
 */
class EntityGroup extends Model
{
    use HasFactory;

    public const STATUS_WAITING_FOR_TRANSCRIPTION = 'WAITING_FOR_TRANSCRIPTION';
    public const STATUS_TRANSCRIBED = 'TRANSCRIBED';
    public const STATUS_WAITING_FOR_AUDIO_SEPARATION = 'WAITING_FOR_AUDIO_SEPARATION';
    public const STATUS_WAITING_FOR_SPLIT = 'WAITING_FOR_SPLIT';
    public const STATUS_WAITING_FOR_WORD_EXTRACTION = 'WAITING_FOR_WORD_EXTRACTION';
    public const STATUS_WAITING_FOR_RETRY = 'WAITING_FOR_RETRY';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_ZIPPED = 'ZIPPED';
    public const STATUS_REPORT = 'REPORT';

    protected $fillable = [
        'name', 'user_id', 'parent_folder_id', 'type',
        'transcription_result', 'transcription_at', 'status', 'meta', 'file_location',
        'result_location', 'deleted_at', 'deleted_at', 'number_of_try', 'description', 'archived_at'
    ];

    protected $casts = [
        'meta' => 'json',
        'result_location' => 'json'
    ];

    /**
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getEntityGroupDepartments(): array
    {
        return Department::query()
            ->select(['departments.id', 'departments.name'])
            ->join('department_files', 'department_files.department_id', '=', 'departments.id')
            ->where('department_files.entity_group_id', $this->id)
            ->distinct()->get()->toArray();
    }

    /**
     * @return HasMany
     */
    public function entities(): HasMany
    {
        return $this->hasMany(Entity::class);
    }

    public function activity(): MorphMany
    {
        return $this->morphMany(Activity::class, 'activity');
    }

    public function scopeTextSearch(Builder $query, string $searchText): Builder
    {
        return $query->where(function ($query) use ($searchText) {
            $query->where('transcription_result', 'LIKE', "%{$searchText}%");
        });
    }

    /**
     * Always create EntityGroup using this function.
     * It sets slug in transaction to make sure EntityGroup will be created using slug.
     *
     * @throws Throwable
     */
    public static function createWithSlug(array $attributes): EntityGroup
    {
        /** @var EntityGroup $entityGroup */
        $entityGroup = DB::transaction(function () use ($attributes) {
            /* @var EntityGroup $e */
            $e = EntityGroup::query()->create($attributes);
            $e->slug = $e->getEntityGroupId();
            $e->save();
            return $e;
        }, 3);

        return $entityGroup;
    }

    /**
     * @throws Exception
     */
    public function getEntityGroupId(): string
    {
        $paddedId = str_pad((string)$this->id, 12, '0', STR_PAD_LEFT);
        $encryptedId = EncryptHelper::encrypt($paddedId);
        return base64_encode($encryptedId);
    }

    public static function convertObfuscatedIdToEntityGroupId(string $obfuscatedId): int
    {
        $base64Decoded = base64_decode($obfuscatedId);
        $decryptedId = EncryptHelper::decrypt($base64Decoded);
        return (int)$decryptedId;
    }

    public function parentFolder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_folder_id');
    }

    public function scopeAvailableNow(Builder $query): Builder
    {
        return $query->where(
            'created_at',
            '<',
            TimeHelper::getLastNMinDivisibleCarbonDateTime(60, now(), 1)->subMinutes(15)
        );
    }

    /**
     * @throws BindingResolutionException
     * @throws Exception
     */
    public function getFileData(bool $getWavFile = false): ?string
    {
        if ($getWavFile) {
            $data = Storage::disk('voice')->get($this->result_location['wav_location'] ?? '');
        } else {
            if (str_contains($this->name, 'tif')) {
                $data = Storage::disk($this->type)->get($this->meta['tif_converted_png_location'] ?? '');
            } else {
                if ($this->type == 'word') {
                    $disk = 'pdf';
                    $fileLocation = $this->result_location['converted_word_to_pdf'] ?? '';
                } else {
                    $disk = $this->type;
                    $fileLocation = $this->file_location;
                }
                $data = Storage::disk($disk)->get($fileLocation);
            }
        }
        if (empty($data)) {
            throw new Exception("Failed to get entity data. EntityGroup id: #$this->id");
        }
        return $data;
    }

    /**
     * @throws BindingResolutionException
     * @throws Exception
     */
    public function getTranscribedFileData(): ?string
    {
        if ($this->type == 'word' && $this->status != EntityGroup::STATUS_TRANSCRIBED) {
            $fileLocation = $this->result_location['converted_word_to_pdf'] ?? '';
        } else {
            $fileLocation = $this->result_location['pdf_location'] ?? '';
        }
        $data = Storage::disk('pdf')->get($fileLocation);
        if (empty($data)) {
            throw new Exception("Failed to get entity data. Entity id: #$this->id");
        }
        return $data;
    }

    public function getExtensionFile(): string
    {
        return strval(pathinfo($this->file_location, PATHINFO_EXTENSION));
    }

    /**
     * @param bool $isBase64
     * @return string|null
     * @throws BindingResolutionException
     * @throws Exception
     */
    public function getHtmlEmbeddableFileData(bool $isBase64 = true): ?string
    {
        if ($this->fileNotExists()) {
            return null;
        }
        $disk = match ($this->type) {
            'voice' => 'voice',
            'image' => 'image',
            'pdf', 'word' => 'pdf',
            'video' => 'video',
            default => throw new Exception('file type does not exists.!')
        };

        if (!$isBase64 && in_array($this->type, ['voice', 'image', 'pdf', 'video', 'word'])) {
            return $this->getFileData() ?? '';
        }

        $fileFormat = pathinfo($this->file_location ?? '', PATHINFO_EXTENSION);
        if ($this->type == 'voice') {
            return 'data:audio/' . $fileFormat . ';base64,' . base64_encode($this->getFileData() ?? '');
        } elseif ($this->type == 'image') {
            return 'data:image/' . $fileFormat . ';base64,' . base64_encode($this->getFileData() ?? '');
        } elseif ($this->type == 'pdf') {
            return 'data:application/pdf;base64,' . base64_encode($this->getFileData() ?? '');
        } elseif ($this->type == 'video') {
            return "data:video/$fileFormat;base64," . base64_encode($this->getFileData() ?? '');
        } elseif ($this->type == 'word') {
            return "data:application/pdf;base64," . base64_encode($this->getFileData() ?? '');
        } else {
            return null;
        }
    }

    /**
     * @param bool $isBase64
     * @return string|null
     * @throws BindingResolutionException
     */
    public function getHtmlEmbeddableTranscribedFileData(bool $isBase64 = true): ?string
    {
        if (!$this->transcribedFileExists()) {
            return null;
        }
        if (in_array($this->type, ['image', 'pdf', 'word'])) {
            if ($isBase64) {
                return 'data:application/pdf;base64,' . base64_encode($this->getTranscribedFileData() ?? '');
            } else {
                return $this->getTranscribedFileData() ?? '';
            }
        } else {
            return null;
        }
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

    /**
     * @throws BindingResolutionException
     */
    public function generateFileDataForEmbedding(bool $isBase64 = true): array
    {
        if (in_array($this->type, ['image', 'pdf', 'word']) && !is_null($this->transcription_result)) {
            if ($this->type == 'image' && $this->status !== EntityGroup::STATUS_TRANSCRIBED) {
                $fileType = 'image';
                $fileName = $this->name;
            } else {
                $fileType = 'pdf';
                $fileName = strval(pathinfo($this->name, PATHINFO_FILENAME)) . '.pdf';
            }
            $fileContent = $this->getHtmlEmbeddableTranscribedFileData($isBase64);
        } else {
            $fileType = $this->type == 'image' ? 'image' : $this->type;
            $fileContent = $this->getHtmlEmbeddableFileData($isBase64);
            $fileName = $this->name;
        }

        return [
            'fileType' => $fileType,
            'fileContent' => $fileContent,
            'fileName' => $fileName
        ];
    }

    public function transcribedFileExists(): bool
    {
        if (!isset($this->result_location['pdf_location'])) {
            return false;
        }

        return Storage::disk('pdf')->exists($this->result_location['pdf_location'] ?? '');
    }

    public function getFileSizeHumanReadable(float|int $sizeInBytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $unitIndex = 0;

        while ($sizeInBytes >= 1024 && $unitIndex < count($units) - 1) {
            $sizeInBytes /= 1024;
            $unitIndex++;
        }

        return round($sizeInBytes, 2) . ' ' . $units[$unitIndex];
    }
}

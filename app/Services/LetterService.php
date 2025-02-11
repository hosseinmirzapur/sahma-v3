<?php

namespace App\Services;

use App\Models\LetterInbox;
use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Models\LetterReply;
use App\Models\LetterSign;
use App\Models\User;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LetterService
{
    /**
     * @throws ValidationException
     */
    public function letterValidation(array $parameters): void
    {
        // Efficiently extract all MIME types
        $mimeTypes = array_unique(array_reduce(config('mime-type', []), function ($carry, $mime) {
            return array_merge($carry, array_keys((array)$mime));
        }, []));

        // Validation rules
        $validator = Validator::make($parameters, [
            'users' => ['required', 'array', 'min:1'],
            'users.*' => ['required', 'integer', 'exists:users,id'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['required', 'file', 'mimes:' . implode(',', $mimeTypes), 'max:5120'],
            'signs' => ['nullable', 'array'],
            'signs.*' => ['nullable', 'integer', 'exists:users,id'],
            'subject' => ['required', 'string'],
            'text' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'string', 'in:' . implode(',', Letter::getAllLetterPriorities())],
            'dueDate' => ['nullable', 'string'], // Removed trailing `|`
            'category' => ['required', 'string', 'in:' . implode(',', Letter::getAllLetterCategories())],
            'referenceType' => ['nullable', 'string', 'in:FOLLOW,REFERENCE'],
            'referenceId' => ['nullable', 'integer', 'exists:letters,id'],
        ]);

        // Throw exception if validation fails
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }


    /**
     * @throws ValidationException
     * @throws Exception
     */
    public function handleLetterSignAndInbox(
        Letter $letter,
        array $receiverUserIds,
        array $signUserIds,
        array $uploadedFiles = []
    ): void {
        // Batch delete existing attachments
        if ($letter->attachments()->exists()) {
            $letter->attachments()->delete();
        }

        // Handle uploaded files
        foreach ($uploadedFiles as $file) {
            $originalFileName = $file->getClientOriginalName();
            $extension = pathinfo($originalFileName, PATHINFO_EXTENSION); // More reliable
            $nowDate = now()->toDateString();
            $now = now()->timestamp;
            $hash = hash('sha3-256', $file);
            $fileName = "$hash-$now.$extension";
            $originalPdfPath = "letter-attachments/$nowDate";

            // Determine storage disk
            $disk = match (strtolower($extension)) {
                "doc", "docx" => "word",
                "jpeg", "jpg", "png", "tif" => 'image',
                "mp4", "avi", "mov", "wmv" => 'video',
                "wav", "mp3", "aac", "flac", "wma", "ogg", "m4a" => 'voice',
                'pdf' => 'pdf',
                default => throw ValidationException::withMessages(['message' => 'فایل آپلود شده پشتیبانی نمیشود.'])
            };

            // Store the file
            if (!$fileLocation = $file->storeAs($originalPdfPath, $fileName, ['disk' => $disk])) {
                throw new Exception('Failed to store file in storage');
            }

            // Create attachment
            $letter->attachments()->create([
                'type' => $disk,
                'file_location' => $fileLocation,
                'meta' => ['original_file_name' => $originalFileName],
            ]);
        }

        // Batch delete letter inboxes
        if ($letter->letterInboxes()->exists()) {
            $letter->letterInboxes()->delete();
        }

        // Validate receiver user IDs in bulk
        $validReceiverIds = User::query()->whereIn('id', $receiverUserIds)->pluck('id')->toArray();
        if (count($validReceiverIds) !== count($receiverUserIds)) {
            throw ValidationException::withMessages(['message' => 'کاربر گیرنده معتبر نیست!']);
        }

        // Bulk insert letter inboxes
        $inboxes = array_map(fn($userId) => ['letter_id' => $letter->id, 'user_id' => $userId], $validReceiverIds);
        LetterInbox::query()->insert($inboxes);

        // Batch delete letter signs
        if ($letter->letterSigns()->exists()) {
            $letter->letterSigns()->delete();
        }

        // Validate sign user IDs in bulk
        $validSignIds = User::query()->whereIn('id', $signUserIds)->pluck('id')->toArray();
        if (count($validSignIds) !== count($signUserIds)) {
            throw ValidationException::withMessages(['message' => 'کاربر گیرنده معتبر نیست!']);
        }

        // Bulk insert letter signs
        $signs = array_map(fn($userId) => ['letter_id' => $letter->id, 'user_id' => $userId], $validSignIds);
        LetterSign::query()->insert($signs);
    }


    /**
     * @param User $user
     * @param array $neededStatuses
     * @return array
     */
    public function getUserLettersByStatusAsArray(User $user, array $neededStatuses): array
    {
        return Letter::query()
            ->where('user_id', $user->id)
            ->whereIn('status', $neededStatuses)
            ->with(['attachments', 'user:id,name', 'letterSigns'])
            ->latest('id')
            ->get()
            ->map(fn(Letter $letter) => $this->transformLetter($letter, $user))
            ->toArray();
    }

    /**
     * @param User $user
     * @return array
     */
    public function getUserInboxLettersAsArray(User $user): array
    {
        return Letter::query()
            ->select('letters.*')
            ->join('letter_inboxes', 'letter_inboxes.letter_id', '=', 'letters.id')
            ->where('letter_inboxes.user_id', $user->id)
            ->whereNotIn('letters.status', [
                Letter::STATUS_DRAFT,
                Letter::STATUS_DELETED,
                Letter::STATUS_ACHIEVED
            ])
            ->distinct('letters.id')
            ->with(['attachments', 'user:id,name', 'letterSigns', 'letterReplies.user'])
            ->latest('letters.updated_at')
            ->get()
            ->map(fn(Letter $letter) => $this->transformInboxLetter($letter, $user))
            ->toArray();
    }

    /**
     * @param User $user
     * @param bool $isArchivedList
     * @param bool $isDeletedList
     * @return array
     */
    public function getUserAllLettersArchivedOrDeletedAsArray(
        User $user,
        bool $isArchivedList = true,
        bool $isDeletedList = true
    ): array {
        return Letter::query()
            ->select('letters.*')
            ->join('letter_inboxes', 'letter_inboxes.letter_id', '=', 'letters.id')
            ->where(fn($query) => $this->applyArchiveOrDeleteFilters($query, $isArchivedList, $isDeletedList))
            ->where(fn($query) => $query->where('letters.user_id', $user->id)
                ->orWhere('letter_inboxes.user_id', $user->id))
            ->distinct('letters.id')
            ->with(['attachments', 'user:id,name', 'letterSigns'])
            ->latest('id')
            ->get()
            ->map(fn(Letter $letter) => $this->transformLetter($letter, $user))
            ->toArray();
    }

    /**
     * @param mixed $query
     * @param bool $isArchivedList
     * @param bool $isDeletedList
     * @return void
     */
    private function applyArchiveOrDeleteFilters(mixed $query, bool $isArchivedList, bool $isDeletedList): void
    {
        if ($isArchivedList) {
            $query->orWhere('letters.status', Letter::STATUS_ACHIEVED);
        }
        if ($isDeletedList) {
            $query->orWhere('letters.status', Letter::STATUS_DELETED);
        }
    }

    /**
     * @param Letter $letter
     * @param User $user
     * @return array
     * @throws Exception
     */
    private function transformLetter(Letter $letter, User $user): array
    {
        return [
            'status' => $letter->getLetterStatus($user),
            'attachment' => $letter->attachments()->exists(),
            'id' => $letter->id,
            'subject' => $letter->subject,
            'sender' => $letter->user->name,
            'description' => $letter->description,
            'dueDate' => $letter->due_date,
            'submittedAt' => timestamp_to_persian_datetime($letter->updated_at),
            'referenceType' => $letter->letter_reference_type ?? null,
            'signUsers' => $letter->letterSigns()->exists(),
            'priority' => $letter->priority,
            'category' => $letter->category
        ];
    }

    /**
     * @param Letter $letter
     * @param User $user
     * @return array
     * @throws Exception
     */
    private function transformInboxLetter(Letter $letter, User $user): array
    {
        return [
            ...$this->transformLetter($letter, $user),
            'read_status' => $letter->letterInboxes()
                ->where('user_id', $user->id)
                ->where('read_status', 1)
                ->exists(),
            'letterSignInfo' => $letter->letterSigns()
                ->where('user_id', $user->id)
                ->pluck('letter_signs.signed_at'),
            'letterReplies' => $letter->letterReplies->map(fn(LetterReply $reply) => [
                'userName' => $reply->user->name,
                'createdAt' => timestamp_to_persian_datetime($reply->created_at),
                'text' => $reply->text,
                'attachments' => $this->getAttachments($reply),
            ])->toArray(),
            'referInfo' => optional(
                $letter->letterInboxes()
                    ->where('user_id', $user->id)
                    ->where('is_refer', 1)
                    ->first(),
                function ($referInfo) {
                    return [
                        'referrerUser' => $referInfo->referrerUser->name ?? null,
                        'referDescription' => $referInfo?->refer_description,
                    ];
                }
            )
        ];
    }

    public function getSignUserInfo(Letter $letter): array
    {
        return $letter->letterSigns()->with('user:id,name')->get()->map(fn(LetterSign $sign) => [
            'id' => $sign->user->id,
            'userName' => $sign->user->name,
            'signedAt' => $sign->signed_at,
        ])->toArray();
    }

    public function getReceiverUsers(Letter $letter): array
    {
        return $letter
            ->letterInboxes()
            ->with('user:id,name,personal_id')
            ->get()
            ->map(fn(LetterInbox $inbox) => [
                'id' => $inbox->user->id,
                'userName' => $inbox->user->name,
                'seen' => (bool)$inbox->read_status,
                'personalId' => (bool)$inbox->user->personal_id,
            ])->toArray();
    }

    public function getAttachments(Letter|LetterReply $letter): array
    {
        return $letter->attachments()->get()->map(fn(LetterAttachment $attachment) => [
            'id' => $attachment->id,
            'fileName' => $attachment->meta['original_file_name'] ?? '',
            'type' => $attachment->type,
            'downloadLink' => route(
                'web.user.cartable.download-attachment',
                ['letterAttachment' => $attachment->id]
            ),
        ])->toArray();
    }

    public function getReplies(Letter $letter, User $user): array
    {
        return $letter->letterReplies()
            ->where(fn($query) => $query->where('recipient_id', $user->id)->orWhere('user_id', $user->id))
            ->orderByDesc('id')
            ->with(['user:id,name', 'attachments'])
            ->get()
            ->map(fn(LetterReply $reply) => [
                'id' => $reply->id,
                'repliedAt' => timestamp_to_persian_datetime($reply->created_at),
                'respondingUser' => $reply->user->name,
                'respondText' => $reply->text,
                'attachments' => $this->getAttachments($reply),
            ])->toArray();
    }

    public function paginationService(int $currentPage, array $queryArrayLetter): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            collect($queryArrayLetter)->forPage($currentPage, 7),
            count($queryArrayLetter),
            7,
            $currentPage,
            ['path' => url()->current()]
        );
    }
}

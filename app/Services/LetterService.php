<?php

namespace App\Services;

use App\Models\LetterInbox;
use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Models\LetterReply;
use App\Models\LetterSign;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LetterService
{
    /**
     * @throws ValidationException
     */
    public function letterValidation(array $parameters): void
    {
        $mimeTypes = [];
        foreach ((array)config('mime-type') as $mime) {
            $mimeTypes = array_merge($mimeTypes, array_keys((array)$mime));
        }

        $validator = Validator::make($parameters, [
            'users' => 'required|array|min:1',
            'users.*' => 'required|integer|exists:users,id',
            'attachments' => 'nullable|array',
            'attachments.*' => 'required|file|mimes:' . implode(',', $mimeTypes) . '|max:5120',
            'signs' => 'nullable|array',
            'signs.*' => 'nullable|integer|exists:users,id',
            'subject' => 'required|string',
            'text' => 'required|string',
            'description' => 'nullable|string',
            'priority' => 'required|string|in:' . implode(',', Letter::getAllLetterPriorities()),
            'dueDate' => 'nullable|string|',
            'category' => 'required|string|in:' . implode(',', Letter::getAllLetterCategories()),
            'referenceType' => 'nullable|string|in:FOLLOW,REFERENCE',
            'referenceId' => 'nullable|integer|exists:letters,id',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->messages();
            $errors = array_map("unserialize", array_unique(array_map("serialize", $errors)));
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @throws ValidationException
     * @throws Exception
     */
    public function handleLetterSignAndInbox(
        Letter $letter,
        array  $receiverUserIds,
        array  $signUserIds,
        array  $uploadedFiles = []
    ): void
    {
        if ($letter->attachments->isNotEmpty()) {
            $letter->attachments()->delete();
        }
        foreach ($uploadedFiles as $file) {
            $originalFileName = $file->getClientOriginalName();

            $extension = $file->extension();

            $nowDate = now()->toDateString();
            $now = now()->timestamp;
            $hash = hash('sha3-256', $file);
            $fileName = "$hash-$now.$extension";
            $originalPdfPath = "letter-attachments/$nowDate";

            $disk = match ($extension) {
                "doc", "docx" => "word",
                "jpeg", "jpg", "png", "tif" => 'image',
                "mp4", "avi", "mov", "wmv" => 'video',
                "wav", "mp3", "aac", "flac", "wma", "ogg", "m4a" => 'voice',
                'pdf' => 'pdf',
                default => throw ValidationException::withMessages(['message' => 'فایل آپلود شده پشتیبانی نمیشود.'])
            };

            $fileLocation = $file->storeAs(
                $originalPdfPath,
                $fileName,
                [
                    'disk' => $disk
                ]
            );

            if ($fileLocation === false) {
                throw new Exception('Failed to store file in storage');
            }
            $meta = ['original_file_name' => $originalFileName];

            $letterAttachment = new LetterAttachment();
            $letterAttachment->type = $disk;
            $letterAttachment->file_location = $fileLocation;
            $letterAttachment->meta = $meta;
            $letterAttachment->attachable()->associate($letter);
            $letterAttachment->save();
        }
        if ($letter->letterInboxes->isNotEmpty()) {
            $letter->letterInboxes()->delete();
        }
        foreach ($receiverUserIds as $userId) {
            $user = User::query()->find($userId);
            if (is_null($user)) {
                throw ValidationException::withMessages(['message' => 'کاربر گیرنده معتبر نیست!']);
            }
            LetterInbox::query()->create([
                'letter_id' => $letter->id,
                'user_id' => $user->id,
            ]);
        }
        if ($letter->letterSigns) {
            $letter->letterSigns()->delete();
        }
        foreach ($signUserIds as $userId) {
            $user = User::query()->find($userId);
            if (is_null($user)) {
                throw ValidationException::withMessages(['message' => 'کاربر گیرنده معتبر نیست!']);
            }
            LetterSign::query()->create([
                'letter_id' => $letter->id,
                'user_id' => $user->id,
            ]);
        }
    }

    public function getUserLettersByStatusAsArray(User $user, array $neededStatuses): array
    {
        return Letter::query()
            ->where('user_id', $user->id)
            ->whereIn('letters.status', $neededStatuses)
            ->latest('id')->get()->map(function (Letter $letter) use ($user) {
                return [
                    'status' => $letter->getLetterStatus($user),
                    'attachment' => !$letter->attachments->isEmpty(),
                    'id' => $letter->id,
                    'subject' => $letter->subject,
                    'sender' => $letter->user->name,
                    'description' => $letter->description,
                    'dueDate' => $letter->due_date,
                    'submittedAt' => timestamp_to_persian_datetime($letter->updated_at),
                    'referenceType' => $letter->letter_reference_type ?? null,
                    'signUsers' => !$letter->letterSigns->isEmpty(),
                    'priority' => $letter->priority,
                    'category' => $letter->category
                ];
            })->toArray();
    }

    /**
     * @throws BindingResolutionException
     */
    public function getUserInboxLettersAsArray(User $user): array
    {
        return Letter::query()
            ->select('letters.*')
            ->join('letter_inboxes', 'letter_inboxes.letter_id', '=', 'letters.id')
            ->where('letter_inboxes.user_id', $user->id)
            ->whereNotIn('letters.status', [Letter::STATUS_DRAFT, Letter::STATUS_DELETED, Letter::STATUS_ACHIEVED])
            ->distinct('letters.id')
            ->latest('letters.updated_at')->get()->map(function (Letter $letter) use ($user) {
                $letterSignInfo = $letter->letterSigns()
                    ->where('user_id', $user->id)
                    ->pluck('letter_signs.signed_at');

                $letterReplies = $letter->letterReplies()->where('recipient_id', $user->id)
                    ->orWhere('user_id', $user->id)
                    ->get()->map(function (LetterReply $reply) {
                        return [
                            'userName' => $reply->user->name,
                            'createdAt' => timestamp_to_persian_datetime($reply->created_at),
                            'text' => $reply->text,
                            'attachments' => $this->getAttachments($reply)
                        ];
                    })->toArray();

                $referInfo = $letter->letterInboxes()
                    ->where('user_id', $user->id)
                    ->where('is_refer', 1)
                    ->first();
                return [
                    'status' => $letter->getLetterStatus($user),
                    'attachment' => !$letter->attachments->isEmpty(),
                    'id' => $letter->id,
                    'read_status' => $letter
                        ->letterInboxes()
                        ->where('user_id', $user->id)
                        ->where('read_status', 1)
                        ->exists(),
                    'subject' => $letter->subject,
                    'sender' => $letter->user->name,
                    'description' => $letter->description,
                    'dueDate' => $letter->due_date,
                    'submittedAt' => timestamp_to_persian_datetime(Carbon::parse($letter->submitted_at)),
                    'referenceType' => $letter->letter_reference_type ?? null,
                    'signUsers' => !$letter->letterSigns->isEmpty(),
                    'priority' => $letter->priority,
                    'category' => $letter->category,
                    'letterSignInfo' => $letterSignInfo,
                    'letterReplies' => $letterReplies,
                    'referInfo' => $referInfo ? [
                        'referrerUser' => $referInfo->referrerUser?->name,
                        'referDescription' => $referInfo?->refer_description,
                    ] : null
                ];
            })->toArray();
    }

    /**
     * @throws ValidationException
     */
    public function getUserAllLettersArchivedOrDeletedAsArray(
        User $user,
        bool $isArchivedList = true,
        bool $isDeletedList = true
    ): array
    {
        $query = Letter::query()
            ->select('letters.*')
            ->join('letter_inboxes', 'letter_inboxes.letter_id', '=', 'letters.id')
            ->where(function ($query) use ($user, $isArchivedList, $isDeletedList) {
                if ($isArchivedList) {
                    $query->orWhere('letters.status', Letter::STATUS_ACHIEVED);
                }
                if ($isDeletedList) {
                    $query->orWhere('letters.status', Letter::STATUS_DELETED);
                }
            })
            ->where(function ($query) use ($user) {
                $query->where('letters.user_id', $user->id)
                    ->orWhere('letter_inboxes.user_id', $user->id);
            })
            ->distinct('letters.id')
            ->latest('id');

        return $query->get()->map(function (Letter $letter) use ($user) {
            return [
                'status' => $letter->getLetterStatus($user),
                'attachment' => !$letter->attachments->isEmpty(),
                'id' => $letter->id,
                'subject' => $letter->subject,
                'sender' => $letter->user->name,
                'description' => $letter->description,
                'dueDate' => $letter->due_date,
                'submittedAt' => timestamp_to_persian_datetime(Carbon::parse($letter->submitted_at)),
                'referenceType' => $letter->letter_reference_type ?? null,
                'signUsers' => !$letter->letterSigns->isEmpty(),
                'priority' => $letter->priority,
                'category' => $letter->category
            ];
        })->toArray();
    }

    public function getSignUserInfo(Letter $letter): array
    {
        return $letter->letterSigns()->get()->map(function (LetterSign $letterSign) {
            return [
                'id' => $letterSign->user->id,
                'userName' => $letterSign->user->name,
                'signedAt' => $letterSign->signed_at,
            ];
        })->toArray();
    }

    public function getReceiverUsers(Letter $letter): array
    {
        return $letter->letterInboxes()->get()->map(function (LetterInbox $inboxLetter) {
            return [
                'id' => $inboxLetter->user->id,
                'userName' => $inboxLetter->user->name,
                'seen' => (bool)$inboxLetter->read_status,
                'personalId' => (bool)$inboxLetter->user->personal_id,
            ];
        })->toArray();
    }

    public function getAttachments(Letter|LetterReply $letter): array
    {
        return $letter->attachments()->get()->map(function (LetterAttachment $letterAttachment) {
            return [
                'id' => $letterAttachment->id,
                'fileName' => $letterAttachment->meta['original_file_name'] ?? '',
                'type' => $letterAttachment->type,
                'downloadLink' => route(
                    'web.user.cartable.download-attachment',
                    ['letterAttachment' => $letterAttachment->id]
                ),
            ];
        })->toArray();
    }

    public function getReplies(Letter $letter, User $user): array
    {
        return $letter
            ->letterReplies()
            ->where(function ($query) use ($user) {
                $query->where('recipient_id', $user->id)
                    ->orWhere('user_id', $user->id);
            })
            ->orderBy('id', 'desc')
            ->get()
            ->map(function (LetterReply $reply) {
                return [
                    'id' => $reply->id,
                    'repliedAt' => timestamp_to_persian_datetime($reply->created_at),
                    'respondingUser' => $reply->user->name,
                    'respondText' => $reply->text,
                    'attachments' => $reply->attachments()->get()->map(function (LetterAttachment $letterAttachment) {
                        return [
                            'id' => $letterAttachment->id,
                            'fileName' => $letterAttachment->meta['original_file_name'] ?? '',
                            'type' => $letterAttachment->type,
                            'downloadLink' => route(
                                'web.user.cartable.download-attachment',
                                ['letterAttachment' => $letterAttachment->id]
                            ),
                        ];
                    })->toArray()
                ];
            })->toArray();
    }

    public function paginationService(int $currentPage, $queryArrayLetter): LengthAwarePaginator
    {
        $perPage = 7;

        $offset = ($currentPage - 1) * $perPage;
        $limitedWorkers = array_slice($queryArrayLetter, $offset, $perPage);

        // create a new LengthAwarePaginator instance
        return new LengthAwarePaginator(
            $limitedWorkers,
            count($queryArrayLetter),
            $perPage,
            $currentPage,
            ['path' => url()->current()]
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\LetterInbox;
use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Models\LetterReply;
use App\Models\LetterSign;
use App\Models\Notification;
use App\Models\User;
use App\Services\LetterService;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Inertia\ResponseFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LetterController extends Controller
{
  private LetterService $letterService;

  /**
   * @throws BindingResolutionException
   */
  public function __construct()
  {
    $this->letterService = app()->make(LetterService::class);

    $this->middleware('check.permission.letter')
      ->only(['show', 'showDrafted', 'submitDrafted', 'submitNotification']);
  }

  /**
   * @throws BindingResolutionException
   */
  public function inbox(Request $request): Response|ResponseFactory
  {
    /* @var User $user */
    $user = $request->user();
    $currentPage = intval($request->query('page', '1'));

    $letters = $this->letterService->getUserInboxLettersAsArray($user);
    $letters = $this->letterService->paginationService($currentPage, $letters);

    return inertia('Dashboard/Inbox/InboxList', [
      'letters' => $letters
    ]);
  }

  public function getDraftedLetters(Request $request): Response|ResponseFactory
  {
    /* @var User $user */
    $user = $request->user();
    $currentPage = intval($request->query('page', '1'));

    $letters = $this->letterService->getUserLettersByStatusAsArray($user, [Letter::STATUS_DRAFT]);
    $letters = $this->letterService->paginationService($currentPage, $letters);

    return inertia('Dashboard/Inbox/InboxList', [
      'letters' => $letters
    ]);
  }

  public function getSubmittedLetters(Request $request): Response|ResponseFactory
  {
    /* @var User $user */
    $user = $request->user();
    $currentPage = intval($request->query('page', '1'));

    $letters = $this->letterService->getUserLettersByStatusAsArray(
      $user,
      [Letter::STATUS_SENT, Letter::STATUS_REPLIED]
    );
    $letters = $this->letterService->paginationService($currentPage, $letters);

    return inertia('Dashboard/Inbox/InboxList', [
      'letters' => $letters
    ]);
  }

  /**
   * @throws ValidationException
   */
  public function getDeletedLetters(Request $request): Response|ResponseFactory
  {
    /* @var User $user */
    $user = $request->user();
    $currentPage = intval($request->query('page', '1'));

    $letters = $this->letterService->getUserAllLettersArchivedOrDeletedAsArray($user, false);
    $letters = $this->letterService->paginationService($currentPage, $letters);

    return inertia('Dashboard/Inbox/InboxList', [
      'letters' => $letters
    ]);
  }

  /**
   * @throws ValidationException
   */
  public function getArchivedLetters(Request $request): Response|ResponseFactory
  {
    /* @var User $user */
    $user = $request->user();
    $currentPage = intval($request->query('page', '1'));

    $letters = $this->letterService->getUserAllLettersArchivedOrDeletedAsArray(
      $user,
      true,
      false
    );
    $letters = $this->letterService->paginationService($currentPage, $letters);

    return inertia('Dashboard/Inbox/InboxList', [
      'letters' => $letters
    ]);
  }

  public function submitForm(): Response|ResponseFactory
  {
    return inertia('Dashboard/Inbox/CreateLetter');
  }

  /**
   * @throws ValidationException
   */
  public function submitAction(Request $request): RedirectResponse
  {
    $this->letterService->letterValidation($request->all());

    /* @var  User $senderUser */
    $senderUser = $request->user();

    $receiverUserIds = (array)$request->input('users');
    $signUserIds = (array)$request->input('signs');

    if (in_array($senderUser->id, $receiverUserIds)) {
      throw ValidationException::withMessages(['message' => 'ارسال نامه برای خود امکان پذیر نیست!.']);
    }

    DB::transaction(function () use ($request, $senderUser, $signUserIds, $receiverUserIds) {
      $dueDate = $request->input('dueDate');
      if (!is_null($dueDate)) {
        $dueDate = Carbon::parse(strval($dueDate));
      }
      $letter = Letter::query()->create([
        'user_id' => $senderUser->id,
        'subject' => strval($request->input('subject')),
        'text' => strval($request->input('text')),
        'status' => Letter::STATUS_SENT,
        'description' => strval($request->input('description')),
        'priority' => strval($request->input('priority')),
        'submitted_at' => now(),
        'due_date' => $dueDate,
        'category' => strval($request->input('category')),
        'letter_reference_type' => strval($request->input('referenceType')),
        'letter_reference_id' => $request->input('referenceId')
      ]);

      $this->letterService->handleLetterSignAndInbox(
        $letter,
        $receiverUserIds,
        $signUserIds,
        $request->attachments
      );
    }, 3);

    return redirect()->route('web.user.cartable.submitted.list');
  }

  /**
   * @throws ValidationException
   */
  public function draftAction(Request $request): RedirectResponse
  {
    $this->letterService->letterValidation($request->all());

    /* @var  User $senderUser */
    $senderUser = $request->user();

    $receiverUserIds = (array)$request->input('users');
    $attachment = (array)$request->input('attachments');
    $signUserIds = (array)$request->input('signs');

    if (in_array($senderUser->id, $receiverUserIds)) {
      throw ValidationException::withMessages(['message' => 'ارسال نامه برای خود امکان پذیر نیست!.']);
    }

    DB::transaction(function () use ($request, $senderUser, $receiverUserIds, $signUserIds) {
      $dueDate = $request->input('dueDate');
      if (!is_null($dueDate)) {
        $dueDate = Carbon::parse(strval($dueDate));
      }
      $letter = Letter::query()->create([
        'user_id' => $senderUser->id,
        'subject' => strval($request->input('subject')),
        'text' => strval($request->input('text')),
        'status' => Letter::STATUS_DRAFT,
        'description' => strval($request->input('description')),
        'priority' => strval($request->input('priority')),
        'due_date' => $dueDate,
        'category' => strval($request->input('category')),
        'letter_reference_type' => $request->input('referenceType'),
        'letter_reference_id' => $request->input('referenceId')
      ]);

      $this->letterService->handleLetterSignAndInbox($letter, $receiverUserIds, $signUserIds);
    }, 3);

    return redirect()->route('web.user.cartable.drafted.list');
  }


  /**
   * @throws Exception
   */
  public function show(Request $request, Letter $letter): Response|ResponseFactory
  {
    /* @var User $user */
    $user = $request->user();

    if (LetterInbox::query()->where([
      'letter_id' => $letter->id,
      'user_id' => $user->id
    ])->exists()) {
      $letterInbox = LetterInbox::query()->where([
        'letter_id' => $letter->id,
        'user_id' => $user->id
      ])->first();

      if (is_null($letterInbox)) {
        throw ValidationException::withMessages(['message' => 'نامه ارسالی وجود ندارد.']);
      }

      $letterInbox->read_status = 1;
      $letterInbox->save();
    }

    $signUsers = $this->letterService->getSignUserInfo($letter);
    $receiverUsers = $this->letterService->getReceiverUsers($letter);
    $attachments = $this->letterService->getAttachments($letter);
    $replies = $this->letterService->getReplies($letter, $user);
    $referenceLetter = null;
    if ($letter->letter_reference_type) {
      $referenceLetter = Letter::find($letter->letter_reference_id);
    }
    $letterSignInfo = $letter->letterSigns()
      ->select(['letter_signs.signed_at'])
      ->where('user_id', $user->id)
      ->get()->toArray();

    $referInfo = $letter->letterInboxes()
      ->where('user_id', $user->id)
      ->where('is_refer', 1)
      ->first();

    return inertia('Dashboard/Inbox/Letter', [
      'status' => $letter->getLetterStatus($user),
      'senderUser' => $letter->user_id,
      'attachment' => $attachments,
      'id' => $letter->id,
      'text' => $letter->text,
      'subject' => $letter->subject,
      'sender' => $letter->user->name,
      'signUsers' => $signUsers,
      'attachments' => $attachments,
      'receiverUsers' => $receiverUsers,
      'description' => $letter->description,
      'dueDate' => $letter->due_date,
      'submittedAt' => timestamp_to_persian_datetime(Carbon::parse($letter->submitted_at)),
      'referenceType' => $letter->letter_reference_type ?? null,
      'referenceId' => $referenceLetter ? $referenceLetter->id : null,
      'category' => $letter->category,
      'priority' => $letter->priority,
      'replies' => $replies,
      'letterSignInfo' => $letterSignInfo,
      'referInfo' => $referInfo ? [
        'referrerUser' => $referInfo->referrerUser?->name,
        'referDescription' => $referInfo?->refer_description,
      ] : null
    ]);
  }

  /**
   * @throws ValidationException
   */
  public function signAction(Request $request, Letter $letter)
  {
    /* @var User $user */
    $user = $request->user();

    if ($letter->letterSigns()->where('user_id', $user->id)->doesntExist()) {
      throw ValidationException::withMessages(['message' => 'شما در لیست امضا کنندگان این نامه قرار ندارید.']);
    }

    $letterSign = LetterSign::query()->where(
      [
        'letter_id' => $letter->id,
        'user_id' => $user->id
      ]
    )->firstOrFail();

    if (!is_null($letterSign->signed_at)) {
      throw ValidationException::withMessages(['message' => 'شما قبلا امضا خود را انجام داده اید.']);
    }

    $letterSign->signed_at = now();
    $letterSign->save();

    return redirect()->back();
  }

  /**
   * @throws Exception
   */
  public function showDrafted(Request $request, Letter $letter): Response|ResponseFactory
  {
    /* @var User $user */
    $user = $request->user();

    $signUsers = $this->letterService->getSignUserInfo($letter);
    $receiverUsers = $this->letterService->getReceiverUsers($letter);

    $referenceLetter = null;
    if ($letter->letter_reference_type) {
      $referenceLetter = Letter::find($letter->letter_reference_id);
    }

    return inertia('Dashboard/Inbox/CreateLetter', [
      'status' => $letter->getLetterStatus($user),
      'attachments' => [],
      'id' => $letter->id,
      'text' => $letter->text,
      'subject' => $letter->subject,
      'sender' => $letter->user->name,
      'signUsers' => $signUsers,
      'receiverUsers' => $receiverUsers,
      'description' => $letter->description,
      'dueDate' => $letter->due_date,
      'submittedAt' => timestamp_to_persian_datetime($letter->updated_at),
      'referenceType' => $letter->letter_reference_type,
      'referenceId' => $referenceLetter ? $referenceLetter->id : null,
      'category' => $letter->category,
      'priority' => $letter->priority
    ]);
  }

  /**
   * @throws ValidationException
   */
  public function submitDrafted(Request $request, Letter $letter): RedirectResponse
  {
    $this->letterService->letterValidation($request->all());

    /* @var  User $senderUser */
    $senderUser = $request->user();

    $receiverUserIds = (array)$request->input('users');
    $attachments = (array)$request->input('attachments');
    $signUserIds = (array)$request->input('signs');
    $dueDate = $request->input('dueDate');

    if (in_array($senderUser->id, $receiverUserIds)) {
      throw ValidationException::withMessages(['message' => 'ارسال نامه برای خود امکان پذیر نیست!.']);
    }

    if (!is_null($dueDate)) {
      $dueDate = Carbon::parse(strval($dueDate));
    }

    $isSubmit = (bool)$request->input('isSubmit');

    $letter->user_id = $senderUser->id;
    $letter->subject = strval($request->input('subject'));
    $letter->text = strval($request->input('text'));
    $letter->description = strval($request->input('description'));
    $letter->priority = strval($request->input('priority'));
    $letter->due_date = $dueDate;
    $letter->category = strval($request->input('category'));
    $letter->letter_reference_type = strval($request->input('referenceType'));
    $letter->letter_reference_id = $request->input('referenceId');
    $uploadedFiles = [];
    if ($isSubmit) {
      $letter->submitted_at = now();
      $letter->status = Letter::STATUS_SENT;
      $uploadedFiles = $attachments;
    }
    $letter->save();

    $this->letterService->handleLetterSignAndInbox($letter, $receiverUserIds, $signUserIds, $uploadedFiles);

    return redirect()->route('web.user.cartable.drafted.list');
  }

  public function submitNotification(Request $request, Letter $letter): RedirectResponse
  {
    $request->validate([
      'letter_id' => 'required|integer|exists:letters,id',
      'subject' => 'required|string',
      'description' => 'required|string',
      'remindAt' => 'required|string|',
      'priority' => 'required|string|in:' . implode(',', Letter::getAllLetterPriorities()),
    ]);

    /* @var  User $senderUser */
    $senderUser = $request->user();

    $remindAt = $request->input('remindAt');
    if (!is_null($remindAt)) {
      $remindAt = Carbon::parse(strval($remindAt));
    }

    Notification::query()->create([
      'user_id' => $senderUser->id,
      'letter_id' => $letter->id,
      'subject' => strval($request->input('subject')),
      'description' => strval($request->input('description')),
      'priority' => strval($request->input('priority')),
      'remind_at' => $remindAt,
    ]);

    return redirect()->back();
  }

  /**
   * @throws ValidationException
   */
  public function tempDelete(Request $request): RedirectResponse
  {
    $request->validate([
      'letters' => 'nullable|array',
      'letters.*' => 'integer|exists:letters,id',
    ]);

    $letterIds = (array)$request->input('letters');

    foreach ($letterIds as $letterId) {
      /* @var Letter $letter */
      $letter = Letter::find($letterId);
      $letter->status = Letter::STATUS_DELETED;
      $letter->save();
    }

    return redirect()->route('web.user.cartable.deleted.list');
  }

  public function archive(Request $request): RedirectResponse
  {
    $request->validate([
      'letters' => 'nullable|array',
      'letters.*' => 'integer|exists:letters,id',
    ]);

    $letterIds = (array)$request->input('letters');

    foreach ($letterIds as $letterId) {
      /* @var Letter $letter */
      $letter = Letter::find($letterId);
      $letter->status = Letter::STATUS_ACHIEVED;
      $letter->save();
    }

    return redirect()->route('web.user.cartable.archived.list');
  }

  /**
   * @throws ValidationException
   */
  public function downloadAttachment(Request $request, LetterAttachment $letterAttachment): StreamedResponse
  {
    /* @var  User $user */
    $user = $request->user();
//    if (
//      $letterAttachment->attachable->user_id != $user->id ||
//      (
//        $letterAttachment->attachable instanceof LetterReply &&
//        $letterAttachment->attachable->letter->letterInboxes()->where('user_id', $user->id)->exists()
//      ) || !$letterAttachment->attachable->letterInboxes()->where('user_id', $user->id)->exists()
//    ) {
//      abort(403, 'نامه متعلق به شما نیست.');
//    }
    if (!Storage::disk($letterAttachment->type)->exists($letterAttachment->file_location)) {
      throw ValidationException::withMessages(['message' => 'فایل مورد نظر موجود نیست.']);
    }
    return Storage::disk($letterAttachment->type)->download(
      $letterAttachment->file_location,
      $letterAttachment->meta['original_file_name'] ?? ''
    );
  }

  /**
   * @throws ValidationException
   */
  public function referAction(Request $request, Letter $letter): RedirectResponse
  {
    $request->validate([
      'users' => 'required|array|min:1',
      'users.*' => 'required|integer|exists:users,id',
      'dueDate' => 'nullable|string|',
      'description' => 'nullable|string',
    ]);

    /* @var  User $referrerUser */
    $referrerUser = $request->user();

    $receiverUserIds = (array)$request->input('users');
    $dueDate = $request->input('dueDate');
    $description = $request->input('description');

    if (!is_null($dueDate)) {
      $dueDate = Carbon::parse(strval($dueDate));
    }

    if (in_array($referrerUser->id, $receiverUserIds)) {
      throw ValidationException::withMessages(['message' => 'ارجاع نامه به خود امکان پذیر نیست!.']);
    }

    foreach ($receiverUserIds as $userId) {
      $user = User::find($userId);
      if (is_null($user)) {
        throw ValidationException::withMessages(['message' => 'کاربر گیرنده معتبر نیست!']);
      }
      LetterInbox::query()->create([
        'letter_id' => $letter->id,
        'user_id' => $user->id,
        'is_refer' => true,
        'due_date' => $dueDate,
        'referred_by' => $referrerUser->id,
        'refer_description' => $description,
      ]);
    }

    return redirect()->back();
  }

  /**
   * @throws ValidationException
   * @throws Exception
   */
  public function replyAction(Request $request, Letter $letter): RedirectResponse
  {
    $mimeTypes = [];
    foreach ((array)config('mime-type') as $mime) {
      $mimeTypes = array_merge($mimeTypes, array_keys((array)$mime));
    }

    $request->validate([
      'users' => 'required|array|min:1',
      'users.*' => 'required|integer|exists:users,id',
      'text' => 'nullable|string',
      'attachments' => 'nullable|array',
      'attachments.*' => 'required|file|mimes:' . implode(',', $mimeTypes) . '|max:5120',
    ]);

    /* @var  User $replyUser */
    $replyUser = $request->user();

    $receiverUserIds = (array)$request->input('users');
    $text = strval($request->input('text'));

    if (
      $replyUser->id !== $letter->user_id &&
      !$letter->letterInboxes()->where('user_id', $replyUser->id)->exists()
    ) {
      throw ValidationException::withMessages(['message' => 'شما مجوز پاسخ به این نامه را ندارید.']);
    }

    foreach ($receiverUserIds as $userId) {
      $user = User::find($userId);
      if (is_null($user)) {
        throw ValidationException::withMessages(['message' => 'کاربر گیرنده معتبر نیست!']);
      }
      $letterReply = LetterReply::query()->create([
        'letter_id' => $letter->id,
        'user_id' => $replyUser->id,
        'text' => $text,
        'recipient_id' => $user->id
      ]);
      if ($letter->letterInboxes()->where('user_id', $user->id)->exists()) {
        /* @var LetterInbox $letterInbox */
        $letterInbox = $letter->letterInboxes()->where('user_id', $user->id)->first();
        $letterInbox->read_status = false;
        $letterInbox->save();
      } else {
        LetterInbox::query()->create([
          'letter_id' => $letter->id,
          'user_id' => $user->id,
        ]);

        foreach ($request->attachments as $file) {
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
          $letterAttachment->attachable()->associate($letterReply);
          $letterAttachment->save();
        }
      }
      $letter->status = Letter::STATUS_REPLIED;
      $letter->save();
    }

    return redirect()->back();
  }
}

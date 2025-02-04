<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'index']);
    Route::get('/login', [AuthController::class, 'loginPage'])->name('login-page');
    Route::post('/login', [AuthController::class, 'loginAction'])->name('login');
});

Route::post('logout/', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth:web')->group(function () {
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('show');
    });

    Route::prefix('cartable')->name('cartable.')->group(function () {
        Route::get('/inbox-list', [LetterController::class, 'inbox'])->name('inbox.list');

        Route::get('/draft-list', [LetterController::class, 'getDraftedLetters'])->name('drafted.list');

        Route::get('/submit-list', [LetterController::class, 'getSubmittedLetters'])->name('submitted.list');

        Route::get('/deleted-list', [LetterController::class, 'getDeletedLetters'])->name('deleted.list');

        Route::get('/archived-list', [LetterController::class, 'getArchivedLetters'])->name('archived.list');

        Route::get('/submit-form', [LetterController::class, 'submitForm'])->name('submit.form');
        Route::post('/submit-action', [LetterController::class, 'submitAction'])->name('submit.action');

        Route::get('/show/{letter}', [LetterController::class, 'show'])->name('letter.show');

        Route::post('/sign/{letter}', [LetterController::class, 'signAction'])->name('sign.action');

        Route::post('/refer/{letter}', [LetterController::class, 'referAction'])->name('refer.action');

        Route::post('/reply/{letter}', [LetterController::class, 'replyAction'])->name('reply.action');

        Route::get('/download-attachment/{letterAttachment}', [LetterController::class, 'downloadAttachment'])
          ->name('download-attachment');

        Route::post('/draft', [LetterController::class, 'draftAction'])->name('draft.action');

        Route::get('/show-draft/{letter}', [LetterController::class, 'showDrafted'])->name('drafted.show');

        Route::post('/submit-draft/{letter}', [LetterController::class, 'submitDrafted'])
          ->name('drafted.submit');

        Route::post('/archive/', [LetterController::class, 'archive'])->name('archive.action');

        Route::post('/temp-delete/', [LetterController::class, 'tempDelete'])->name('temp-delete.action');

        Route::post('/submit-reminder/{letter}', [LetterController::class, 'submitNotification'])
          ->name('submit.reminder.action');
    });

    Route::prefix('notification')->name('notification.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/create', [NotificationController::class, 'createAction'])->name('create.action');
    });

    Route::prefix('department')->name('department.')->group(function () {
        Route::get('/list', [DepartmentController::class, 'index'])->name('index');
        Route::post('/create', [DepartmentController::class, 'create'])->name('create');
        Route::post('/edit/{department}', [DepartmentController::class, 'edit'])->name('edit');
        Route::post('/delete/{department}', [DepartmentController::class, 'delete'])->name('delete');
    });

    Route::middleware('check.permission.folder-and-file-management')
      ->prefix('report')->name('report.')->group(function () {
        Route::get('/users', [ReportController::class, 'usersReport'])->name('users');

        Route::get('/create-excel-users', [ReportController::class, 'createExcelUserReport'])
          ->name('create-excel-users');

        Route::get('/total-uploaded-files', [ReportController::class, 'totalUploadedFiles'])
          ->name('total-files');

        Route::get('/total-uploaded-files-type', [ReportController::class, 'totalUploadedFileByType'])
        ->name('total-files-by-type');

        Route::get('/total-uploaded-transcribe-files', [ReportController::class, 'totalTranscribedFiles'])
        ->name('total-transcribe-files');
      });

    Route::prefix('dashboard')->name('dashboard.')->middleware('web')->group(function () {
        Route::get('/', [DashboardController::class, 'dashboard'])->name('index');

        Route::middleware('check.permission.folder-and-file-management')->group(function () {
            Route::post('/copy/', [DashboardController::class, 'copy'])->name('copy');

            Route::post('/move/', [DashboardController::class, 'move'])->name('move');

            Route::post('/delete/', [DashboardController::class, 'permanentDelete'])->name('permanent-delete');

            Route::get('/trash', [DashboardController::class, 'trashList'])->name('trash-list');
            Route::post('/trash', [DashboardController::class, 'trashAction'])->name('trash-action');
            Route::post('/trash-retrieve/', [DashboardController::class, 'trashRetrieve'])
              ->name('trash-retrieve');

            Route::get('/archive/', [DashboardController::class, 'archiveList'])->name('archive-list');
            Route::post('/archive/', [DashboardController::class, 'archiveAction'])->name('archive-action');
            Route::post('/archive-retrieve/', [DashboardController::class, 'archiveRetrieve'])
            ->name('archive-retrieve');

            Route::post('/create-zip/', [DashboardController::class, 'createZip'])->name('create-zip');
        });

        Route::get('/search', [DashboardController::class, 'searchForm'])->name('search-form');

        Route::post('/search', [DashboardController::class, 'searchAction'])->name('search-action');

        Route::prefix('folder')->name('folder.')->group(function () {
            Route::get('/show/{folderId?}', [FolderController::class, 'show'])->name('show');
            Route::post('/create-root/', [FolderController::class, 'createRoot'])->name('create-root');
            Route::middleware([
            'convert.obfuscatedId-folder',
            'check.permission.folder-and-file-management'
            ])->group(function () {
                Route::post('/create/{folderId?}', [FolderController::class, 'create'])->name('create');

                Route::post('/rename/{folderId?}', [FolderController::class, 'rename'])->name('rename');
            });
        });

        Route::prefix('file')->name('file.')->group(function () {
            Route::get('/show/{fileId?}', [FileController::class, 'show'])->name('show');
            Route::post('/add-description/{fileId?}', [FileController::class, 'addDescription'])
            ->name('add-description');

            Route::middleware(['convert.obfuscatedId-entityGroup', 'check.permission.folder-and-file-management'])
            ->group(function () {
                Route::post('/transcribe-file/{fileId?}', [FileController::class, 'transcribe'])
                ->name('transcribe');
                Route::get('/download-original-file/{fileId?}', [FileController::class, 'downloadOriginalFile'])
                ->name('download.original-file');

                Route::get(
                    '/download-searchable-file/{fileId?}',
                    [FileController::class, 'downloadSearchAbleFile']
                )->name('download.searchable');

                Route::get('/download-word-file/{fileId?}', [FileController::class, 'downloadWordFile'])
                ->name('download.word');

                Route::post('/rename/{fileId?}', [FileController::class, 'rename'])->name('rename');

                Route::get('/print/{fileId?}', [FileController::class, 'printOriginalFile'])
                ->name('print.original');
            });

            Route::post('/upload/{folderId?}', [FileController::class, 'upload'])->name('upload');

            Route::post('/upload-root/', [FileController::class, 'uploadRoot'])->name('create-root');
            Route::post('/modify-departments/{fileId?}', [FileController::class, 'modifyDepartments'])->name('modify-departments');
        });
    });

    Route::prefix('user-management')->name('user-management.')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');

        Route::get('/{user}', [UserManagementController::class, 'userInfo'])->name('user-info');

        Route::post('/create-user', [UserManagementController::class, 'create'])->name('create-user');

        Route::post('/delete-user/{user}', [UserManagementController::class, 'block'])->name('delete-user');

        Route::post('/edit-user/{user}', [UserManagementController::class, 'edit'])->name('edit-user');

        Route::post('/search', [UserManagementController::class, 'search'])->name('search');
    });

    Route::prefix('api')->name('api.')->group(function () {
        Route::post('/users', [SearchController::class, 'listUsers'])->name('users');
        Route::post('/letters', [SearchController::class, 'listLetters'])->name('letters');
    });
});

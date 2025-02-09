<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Department;
use App\Models\EntityGroup;
use App\Models\User;
use Carbon\CarbonPeriod;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Inertia\ResponseFactory;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;

class ReportController extends Controller
{
    public function totalUploadedFiles(): Response|ResponseFactory
    {
        $now = now();
        $startDate = $now->subDays(15)->startOfDay();
        $endDate = $now->endOfDay();

        // Retrieve grouped count of files uploaded per day
        $historyTotalFilesInDate = EntityGroup::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'date');

        // Generate a full date range with default values of 0
        $historyTotalFilesIn = collect(CarbonPeriod::create($startDate, $now))
            ->mapWithKeys(fn($date) => [
                intval($date->timestamp) => $historyTotalFilesInDate->get($date->format('Y-m-d'), 0)
            ])
            ->all();

        // Filter activities only once
        $activities = EntityGroup::query()->whereNotIn('type', ['excel', 'zip']);

        $lastWeekCountFiles = (clone $activities)
            ->whereBetween('created_at', [
                $now->subWeek()->startOfDay(), $now->endOfDay()
            ])
            ->count();
        $lastMonthCountFiles = (clone $activities)
            ->whereBetween('created_at', [
                $now->subMonth()->startOfDay(), $now->endOfDay()
            ])
            ->count();
        $totalTodayFiles = (clone $activities)
            ->whereBetween('created_at', [
                $now->startOfDay(), $now->endOfDay()
            ])
            ->count();

        return inertia('Dashboard/Reports/Report', [
            'historyTotalFilesIn' => $historyTotalFilesIn,
            'totalTodayFiles' => $totalTodayFiles,
            'lastMonthCountFiles' => $lastMonthCountFiles,
            'lastWeekCountFiles' => $lastWeekCountFiles
        ]);
    }


    public function totalUploadedFileByType(): Response|ResponseFactory
    {
        $totalUploadedFileGroupByType = EntityGroup::query()
            ->select('type', DB::raw('count(*) as count'))
            ->whereNotIn('type', ['zip', 'excel'])
            ->groupBy('type')
            ->get();

        return inertia('Dashboard/Reports/ReportFileType', [
            'totalUploadedFileGroupByType' => $totalUploadedFileGroupByType,
        ]);
    }

    public function totalTranscribedFiles(): Response|ResponseFactory
    {
        $totalUploadedFileByTranscriptionStatus = EntityGroup::query()->selectRaw(
            'CASE WHEN status = "TRANSCRIBED" THEN "TRANSCRIBED" ELSE "Other Statuses" END AS status_group'
        )
            ->selectRaw('COUNT(*) as count')
            ->whereNotIn('type', ['zip', 'excel']) // Exclude rows with 'type' equal to 'zip'
            ->groupBy('status_group')
            ->get();

        return inertia('Dashboard/Reports/ReportTranscribeFile', [
            'totalUploadedFileByTranscriptionStatus' => $totalUploadedFileByTranscriptionStatus
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function usersActivity(Request $request): Response|ResponseFactory
    {
        /* @var User $user */
        $user = $request->user();

        if (!$user->is_super_admin) {
            throw ValidationException::withMessages(['message' => 'مجوز ورود به این بخش را ندارید.']);
        }
        $activities = Activity::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->get()
            ->map(function (Activity $activity) {
                return [
                    'status' => match ($activity->status) {
                        Activity::TYPE_MOVE => 'انتقال',
                        Activity::TYPE_RETRIEVAL => 'بازیابی',
                        Activity::TYPE_TRANSCRIPTION => 'بررسی هوشمند',
                        Activity::TYPE_ARCHIVE => 'آرشیو',
                        Activity::TYPE_DELETE => 'حذف',
                        Activity::TYPE_EDIT => 'ویرایش',
                        Activity::TYPE_RENAME => 'تغییر نام',
                        Activity::TYPE_UPLOAD => 'بارگذاری',
                        Activity::TYPE_CREATE => 'ایجاد',
                        Activity::TYPE_DOWNLOAD => 'دانلود',
                        default => throw ValidationException::withMessages(['message' => 'unsupported status.'])
                    },
                    'type' => match ($activity->activity_type) {
                        'App\Models\EntityGroup' => 'فایل',
                        'App\Models\User' => 'کاربران',
                        'App\Models\Folder' => 'پوشه',
                        default => throw ValidationException::withMessages(['message' => 'unsupported model.'])
                    },
                    'description' => $activity->description,
                    'created_at' => $activity->created_at
                ];
            });
        return inertia('Dashboard/Reports/UserReport', ['activities' => $activities]);
    }

    /**
     * @throws Exception
     */
    public function usersReport(Request $request): Response|ResponseFactory
    {
        $onlineUserReportType = $request->input('onlineUserReportTypeByDate');
        $reportHistoryData = Activity::query()
            ->where('status', Activity::TYPE_LOGIN)
            ->select([DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count')])
            ->where('created_at', '>', now()->subDays(6))
            ->where('created_at', '<', now())
            ->groupBy(DB::raw('DATE(created_at)'))
            ->distinct()
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item['date'] => $item['count']];
            });
        $period = CarbonPeriod::create(now()->subDays(6), now());
        $reportHistory = [];
        foreach ($period as $date) {
            if (is_null($date)) {
                continue;
            }
            if (isset($reportHistoryData[$date->format('Y-m-d')])) {
                $reportHistory[intval($date->timestamp)] =
                    $reportHistoryData[$date->format('Y-m-d')];
            } else {
                $reportHistory[intval($date->timestamp)] = 0;
            }
        }
        // Fetch login and logout activities for the specified period
        $activities = Activity::query()
            ->whereIn('status', [Activity::TYPE_LOGIN, Activity::TYPE_LOGOUT]);

        if ($onlineUserReportType == 'yesterday') {
            $start = now()->subDay()->startOfDay();
            $end = now()->subDay()->endOfDay();
        } elseif ($onlineUserReportType == 'lastWeek') {
            $start = now()->subWeek()->startOfDay();
            $end = now()->endOfDay();
        } elseif ($onlineUserReportType == 'lastMonth') {
            $start = now()->subMonth()->startOfDay();
            $end = now()->endOfDay();
        } else {
            $start = now()->startOfDay();
            $end = now()->endOfDay();
        }

        $lastWeekCountReport = $activities
            ->forPeriod(now()->subWeek()->startOfDay(), now()->endOfDay())
            ->distinct('user_id')
            ->count();

        $lastMonthCountReport = $activities
            ->forPeriod(now()->subMonth()->startOfDay(), now()->endOfDay())
            ->distinct('user_id')
            ->count();

        $totalTodayUsers = $activities
            ->forPeriod(now()->startOfDay(), now()->endOfDay())
            ->distinct('user_id')
            ->count();

        $report = [];

        // Group activities by user ID and initialize user data
        foreach ($activities->forPeriod($start, $end)->get() as $activity) {
            $userId = $activity->user_id;
            $date = $activity->created_at->toDateString();
            /** @phpstan-ignore-line */

            if (!isset($report[$date][$userId])) {
                $report[$date][$userId] = [
                    'loginDates' => [],
                    'logoutDates' => [],
                ];
            }

            // Add login or logout date to the respective array
            if ($activity->status == Activity::TYPE_LOGIN) {
                $report[$date][$userId]['loginDates'][] = timestamp_to_persian_datetime($activity->created_at);
            } elseif ($activity->status == Activity::TYPE_LOGOUT) {
                $report[$date][$userId]['logoutDates'][] = timestamp_to_persian_datetime($activity->created_at);
            }
            if (!isset($report[$date][$userId]['personalId'])) {
                $departments = Department::query()->select(['departments.id', 'departments.name'])
                    ->join('department_users', 'department_users.department_id', '=', 'departments.id')
                    ->where('department_users.user_id', '=', $userId)
                    ->get()
                    ->toArray();
                $report[$date][$userId]['personalId'] = $activity->user->personal_id;
                $report[$date][$userId]['name'] = $activity->user->name;
                $report[$date][$userId]['departments'] = $departments;
            }
        }

        $reportFileInfo = strval(session('reportFileInfo', null));
        session()->forget('zipFileInfo');
        return inertia('Dashboard/Reports/UserReport', [
            'reportHistory' => $reportHistory,
            'onlineUsersToday' => $totalTodayUsers,
            'reports' => $report,
            'reportFileInfo' => json_decode($reportFileInfo, true),
            'lastMonthCountReport' => $lastMonthCountReport,
            'lastWeekCountReport' => $lastWeekCountReport
        ]);
    }

    /**
     * @throws Throwable
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function createExcelUserReport(Request $request): RedirectResponse
    {
        /* @var User $user */
        $user = $request->user();

        $onlineUserReportType = strval($request->input('onlineUserReportTypeByDate', ''));

        // Fetch login and logout activities for the specified period
        $activities = Activity::query()
            ->whereIn('status', [Activity::TYPE_LOGIN, Activity::TYPE_LOGOUT]);

        if ($onlineUserReportType == 'yesterday') {
            $start = now()->subDay()->startOfDay();
            $end = now()->subDay()->endOfDay();
        } elseif ($onlineUserReportType == 'lastWeek') {
            $start = now()->subWeek()->startOfDay();
            $end = now()->endOfDay();
        } elseif ($onlineUserReportType == 'lastMonth') {
            $start = now()->subMonth()->startOfDay();
            $end = now()->endOfDay();
        } else {
            $start = now()->startOfDay();
            $end = now()->endOfDay();
        }
        $report = [];

        // Group activities by user ID and initialize user data
        foreach ($activities->forPeriod($start, $end)->get() as $activity) {
            $userId = $activity->user_id;
            $date = $activity->created_at->toDateString();
            /** @phpstan-ignore-line */

            if (!isset($report[$date][$userId])) {
                $report[$date][$userId] = [
                    'loginDates' => [],
                    'logoutDates' => [],
                ];
            }

            // Add login or logout date to the respective array
            if ($activity->status == Activity::TYPE_LOGIN) {
                $report[$date][$userId]['loginDates'][] = timestamp_to_persian_datetime($activity->created_at);
            } elseif ($activity->status == Activity::TYPE_LOGOUT) {
                $report[$date][$userId]['logoutDates'][] = timestamp_to_persian_datetime($activity->created_at);
            }
            if (!isset($report[$date][$userId]['personalId'])) {
                $departments = Department::query()->select(['departments.id', 'departments.name'])
                    ->join(
                        'department_users',
                        'department_users.department_id',
                        '=',
                        'departments.id'
                    )
                    ->where('department_users.user_id', '=', $userId)
                    ->get()
                    ->toArray();
                $report[$date][$userId]['personalId'] = $activity->user->personal_id;
                $report[$date][$userId]['name'] = $activity->user->name;
                $report[$date][$userId]['departments'] = $departments;
            }
        }

        $spreedSheet = new Spreadsheet();
        $sheet = $spreedSheet->getActiveSheet()->setRightToLeft(true);
        $sheet->setCellValue('A1', 'نام و نام خانوادگی');
        $sheet->setCellValue('B1', 'کد پرسنلی');
        $sheet->setCellValue('C1', 'واحد');
        $sheet->setCellValue('D1', 'زمان ورود');
        $sheet->setCellValue('E1', 'زمان خروج');

        $row = 2;
        foreach ($report as $date) {
            foreach ($date as $userId => $data) {
                $departmentNames = array_map(function ($department) {
                    return $department['name'] ?? '';
                    /** @phpstan-ignore-line */
                }, $data['departments'] ?? []);
                $sheet->setCellValue("A$row", $data['name'] ?? '');
                $sheet->setCellValue("B$row", $data['personalId'] ?? '');
                $sheet->setCellValue(
                    "C$row",
                    strval(implode(PHP_EOL, $departmentNames))
                );
                $sheet->setCellValue(
                    "D$row",
                    strval(implode(PHP_EOL, $data['loginDates'] ?? []))/** @phpstan-ignore-line */
                );
                $sheet->setCellValue(
                    "E$row",
                    strval(implode(PHP_EOL, $data['logoutDates'] ?? []))/** @phpstan-ignore-line */
                );
                $row++;
            }
        }

        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        $sheet->getStyle('A:E')->getAlignment()->setHorizontal('center');

        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $nowDateString = timestamp_to_persian_datetime(now()->timestamp, false);

        $fileName = "report-users-$onlineUserReportType.xlsx";

        $writer = IOFactory::createWriter($spreedSheet, 'Xlsx');
        $tmpAddress = "/tmp/$fileName";
        $writer->save($tmpAddress);
        $storageAddress = now()->toDateString() . "/$fileName";
        /** @phpstan-ignore-next-line */
        Storage::disk('excel')->put($storageAddress, file_get_contents($tmpAddress));

        unlink($tmpAddress);

        $entityGroup = EntityGroup::createWithSlug([
            'user_id' => $user->id,
            'name' => $fileName,
            'type' => 'excel',
            'status' => EntityGroup::STATUS_REPORT,
            'file_location' => $storageAddress
        ]);

        $downloadUrl = strval(
            route('web.user.dashboard.file.download.original-file', ['fileId' => $entityGroup->getEntityGroupId()])
        );
        $reportFileInfo = [
            'downloadUrl' => $downloadUrl,
            'reportFileSize' => $entityGroup->getFileSizeHumanReadable(
                intval(Storage::disk($entityGroup->type)->size($entityGroup->file_location))
            ),
            'reportFileName' => $fileName
        ];
        return redirect()->back()->with(['reportFileInfo' => json_encode($reportFileInfo)]);
    }
}

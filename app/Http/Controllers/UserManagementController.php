<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Department;
use App\Models\DepartmentUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Inertia\ResponseFactory;

class UserManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('check.permission.user-management')
        ->only(['index', 'create', 'block', 'delete', 'edit', 'search']);
    }

    public function index(Request $request, UserService $userService): Response|ResponseFactory
    {
      /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

        $searchedUser = session('searchedUser', null);

        if ($searchedUser) {
            $users = json_decode(strval($searchedUser), true);
            session()->forget('searchedUser');
        } else {
            $users = $userService->getUserInfo($user);
        }

        return inertia('Dashboard/UserManagement/UserManagement', ['users' => $users]);
    }

    public function userInfo(Request $request, User $user): Response|ResponseFactory
    {
        $departments = Department::query()->select('departments.id', 'departments.name')
        ->join(
            'department_users',
            'department_users.department_id',
            '=',
            'departments.id'
        )->where('department_users.user_id', $user->id)->get()->toArray();
        if ($user->is_super_admin) {
            $permission = 'super_admin';
        } elseif ($user->role->permission->full) {
            $permission = 'full';
        } elseif ($user->role->permission->modify) {
            $permission = 'modify';
        } else {
            $permission = 'read_only';
        }
        $userInfo =
        [
        'id' => $user->id,
        'name' => $user->name,
        'personalId' => $user->personal_id,
        'password' => $user->password,
        'roleTitle' => $user->role->title,
        'departments' => $departments,
        'permission' => $permission
        ];

        $activities = Activity::query()
        ->select(['description', 'created_at'])
        ->where('user_id', $user->id)
        ->orderBy('id', 'desc')
        ->get()->map(function (Activity $activity) {
            return [
            'created_at' => timestamp_to_persian_datetime($activity->created_at),
            'description' => $activity->description
            ];
        })
        ->toArray();

        return inertia('Components/UserInfo', [
        'userInfo' => $userInfo,
        'activities' => $activities
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $request->validate([
        'name' => 'required|string',
        'personalId' => 'required|string',
        'roleTitle' => 'required|string',
        'departments' => 'required|array|min:1',
        'departments.*' => 'integer|exists:departments,id',
        'password' => 'required|string|min:6|confirmed',
        'permission' => 'required|in:full,modify,read_only'
        ]);
        DB::transaction(function () use ($request) {
          /* @var User $adminUser */
            $adminUser = $request->user();

            $name = strval($request->input('name'));
            $personalId = intval($request->input('personalId'));

            if (User::query()->where('personal_id', $personalId)->exists()) {
                throw ValidationException::withMessages(['message' => 'کاربری با این کد پرسنلی وجود دارد.']);
            }

            $roleTitle = strval($request->input('roleTitle'));
            $departments = (array)$request->input('departments');
            $password = strval($request->input('password'));
            $permission = strval($request->input('permission'));

            $role = Role::query()->create(['title' => $roleTitle, 'slug' => $roleTitle]);

            Permission::query()->create([
            'full' => $permission === 'full',
            'modify' => $permission === 'modify',
            'read_only' => $permission === 'read_only',
            'role_id' => $role->id
            ]);

            $user = User::query()->create([
            'name' => $name,
            'personal_id' => $personalId,
            'password' => Hash::make($password),
            'created_by' => $adminUser->id,
            'role_id' => $role->id,
            'is_super_admin' => false
            ]);

            foreach ($departments as $departmentId) {
                $department = Department::query()->findOrFail($departmentId);
                DepartmentUser::query()->create([
                'user_id' => $user->id,
                'department_id' => $department->id
                ]);
            }
            $description = "کاربر $user->name توسط کاربر {$adminUser->name} با کد پرسنلی  {$user->personal_id}ایجاد شد.";
            $activity = new Activity();
            $activity->user_id = $adminUser->id;
            $activity->status = Activity::TYPE_CREATE;
            $activity->description = $description;
            $activity->activity()->associate($user);
            $activity->save();
        }, 3);
        return redirect()->back()->with(['message' => 'کاربر با موفقیت اضافه شد.']);
    }

  /**
   * @throws ValidationException
   */
    public function block(Request $request, User $user): RedirectResponse
    {
      /* @var User $adminUser */
        $adminUser = $request->user();
        if ($user->is_super_admin) {
            throw ValidationException::withMessages(['message' => 'مدیر سیستم قابلیت حذف ندارد.']);
        }

        DB::transaction(function () use ($user, $adminUser) {
            $user->deleted_at = now();
            $user->password = 'DELETED_' . $user->password;
            $user->save();

            $description = "کاربر $user->name توسط کاربر {$adminUser->name} با کد پرسنلی {$user->personal_id}حذف شد. ";
            $activity = new Activity();
            $activity->user_id = $adminUser->id;
            $activity->status = Activity::TYPE_DELETE;
            $activity->description = $description;
            $activity->activity()->associate($user);
            $activity->save();
        }, 3);

        return redirect()->route('web.user.user-management.index');
    }

    public function delete(Request $request, User $user): RedirectResponse
    {
      /* @var User $adminUser */
        $adminUser = $request->user();

        DB::transaction(function () use ($user, $adminUser) {
            $user->delete();

            $description = "کاربر $user->name توسط کاربر  {$adminUser->name} با کد پرسنلی
            {$user->personal_id}حذف شد. ";

            $activity = new Activity();
            $activity->user_id = $adminUser->id;
            $activity->status = Activity::TYPE_DELETE;
            $activity->description = $description;
            $activity->activity()->associate($user);
            $activity->save();
        }, 3);

        return redirect()->back()->with(['message' => 'کاربر با موفقیت مسدود شد.']);
    }

  /**
   * @throws ValidationException
   */
    public function edit(Request $request, User $user): RedirectResponse
    {
        $request->validate([
        'name' => 'required|string',
        'personalId' => 'required|integer',
        'roleTitle' => 'required|string',
        'departments' => 'required|array|min:1',
        'departments.*' => 'integer|exists:departments,id',
        'password' => 'nullable|string|min:6|confirmed',
        'permission' => 'required|in:full,modify,read_only'
        ]);

        if ($user->is_super_admin) {
            throw ValidationException::withMessages(['message' => 'مدیر سیستم قابلیت ویرایش را ندارد.']);
        }

        DB::transaction(function () use ($request, $user) {
          /* @var User $adminUser */
            $adminUser = $request->user();

            $name = strval($request->input('name')) !== $user->name ?
            strval($request->input('name')) :
            $user->name;

            $personalId = intval($request->input('personalId')) !== $user->personal_id ?
            intval($request->input('personalId')) :
            $user->personal_id;

            $roleTitle = strval($request->input('roleTitle'));
            $permissionInput = strval($request->input('permission'));
            $password = $request->input('password') ?? null;
            $departments = (array)$request->input('departments');
            $oldRole = $user->role;
            $newRole = null;
            if ($oldRole->title !== $roleTitle) {
                $newRole = Role::query()->create(['title' => $roleTitle, 'slug' => $roleTitle]);
                Permission::query()->create([
                'full' => $permissionInput === 'full',
                'modify' => $permissionInput === 'modify',
                'read_only' => $permissionInput === 'read_only',
                'role_id' => $newRole->id
                ]);
            }
            if ($newRole == null) {
                $permission = $oldRole->permission;
                $permission->full = $permissionInput == 'full';
              /** @phpstan-ignore-line */
                $permission->modify = $permissionInput == 'modify';
              /** @phpstan-ignore-line */
                $permission->read_only = $permissionInput == 'read_only';
              /** @phpstan-ignore-line */
                $permission->save();
            }

            $user->name = $name;
            $user->personal_id = $personalId;
            $user->role_id = $newRole == null ? $oldRole->id : $newRole->id;
            if ($password != null) {
                $password = strval($password);
                if (Hash::check($password, strval($user->password))) {
                    if (!str_starts_with(strval($user->password), '$argon2id$')) {
                        $user->password = Hash::make($password);
                    }
                } else {
                    $user->password = Hash::make($password);
                }
            }
            $user->save();

            if ($newRole != null && Permission::query()->where('role_id', $newRole->id)->exists()) {
                $oldRole->permission()->delete();
                $oldRole->delete();
            }

            foreach ($departments as $departmentId) {
              /* @var Department $department */
                $department = Department::query()->findOrFail($departmentId);
                if (
                    DepartmentUser::query()
                    ->where('user_id', $user->id)
                    ->where('department_id', $department->id)
                    ->doesntExist()
                ) {
                    DepartmentUser::query()->create([
                    'user_id' => $user->id,
                    'department_id' => $department->id
                    ]);
                }
            }

            $departmentUsers = DepartmentUser::query()->where('user_id', $user->id)->get();

            foreach ($departmentUsers as $departmentUser) {
                if (!in_array($departmentUser->department_id, $departments)) {
                    $departmentUser->delete();
                }
            }
            $description = "کاربر $user->name توسط کاربر  {$adminUser->name} با کد پرسنلی
            {$user->personal_id}ویرایش شد. ";

            $activity = new Activity();
            $activity->user_id = $adminUser->id;
            $activity->status = Activity::TYPE_EDIT;
            $activity->description = $description;
            $activity->activity()->associate($user);
            $activity->save();
        }, 3);
        return redirect()->back()->with(['message' => 'کاربر با موفقیت ویرایش شد.']);
    }

    public function search(Request $request, UserService $userService): RedirectResponse
    {
        $identifier = strval($request->input('identifier'));

        if (is_numeric($identifier)) {
            $users = User::query()->where('personal_id', $identifier)->get();
        } else {
            $users = User::query()->where('name', 'LIKE', '%' . $identifier . '%')->get();
        }
        if ($users->isNotEmpty()) {
            $users = $userService->getUsersDepartments($users);
        } else {
            $users = [];
        }

        return redirect()->back()->with(['searchedUser' => json_encode($users)]);
    }
}

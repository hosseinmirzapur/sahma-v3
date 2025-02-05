<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DepartmentUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;
use Inertia\ResponseFactory;

class DepartmentController extends Controller
{
    public function index(Request $request): Response|ResponseFactory
    {
        /* @var  User $user */
        $user = $request->user();

        if (!$user->is_super_admin) {
            abort(403, 'شما به این بخش دسترسی ندارید.');
        }
        return inertia('Dashboard/UserManagement/Department', ['departments' => Department::all()]);
    }

    public function create(Request $request): RedirectResponse
    {
        $request->validate([
            'departmentName' => ['required', 'string']
        ]);

        /* @var  User $user */
        $user = $request->user();

        if (!$user->is_super_admin) {
            abort(403, 'شما به این بخش دسترسی ندارید.');
        }
        $departmentName = strval($request->input('departmentName'));

        DB::transaction(function () use ($departmentName, $user) {

            $department = Department::query()->create([
                'name' => $departmentName,
                'created_by' => $user->id
            ]);

            DepartmentUser::query()->create([
                'user_id' => $user->id,
                'department_id' => $department->id
            ]);
        }, 3);

        return redirect()->back();
    }

    public function edit(Request $request, Department $department): RedirectResponse
    {
        $request->validate([
            'departmentName' => 'required|string'
        ]);

        /* @var  User $user */
        $user = $request->user();

        if (!$user->is_super_admin) {
            abort(403, 'شما به این بخش دسترسی ندارید.');
        }

        $department->name = strval($request->input('departmentName'));
        $department->save();

        return redirect()->back();
    }

    public function delete(Request $request, Department $department): RedirectResponse
    {
        /* @var  User $user */
        $user = $request->user();

        if (!$user->is_super_admin) {
            abort(403, 'شما به این بخش دسترسی ندارید.');
        }

        $department->delete();

        return redirect()->back();
    }
}

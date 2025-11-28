<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use App\Traits\ApiResponse;

class UserController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // return all users as JSON
        try {
            $users = User::with('roles:id,name')->get();
            return $this->success(
            data: $users,
            message: 'Users retrieved successfully.'
        );
        } catch (\Exception $e) {
            return $this->error(
                message: "Somthing went wrong" . $e->getMessage(),
            );
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function assignRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => [
                'required',
                'string',
                Rule::exists('roles', 'name')->where('guard_name', config('permission.defaults.guard')),
            ],
        ]);

        $user->assignRole($validated['role']);

        return $this->success(
            data: $user->load('roles:id,name'),
            message: 'Role assigned successfully.'
        );
    }

    public function removeRole(User $user, string $role)
    {
        $roleModel = Role::where('name', $role)
            ->where('guard_name', config('permission.defaults.guard'))
            ->first();

        if (!$roleModel) {
            return $this->error(
                message: 'Role not found.',
                status: 404
            );
        }

        $user->removeRole($roleModel->name);

        return $this->success(
            data: $user->load('roles:id,name'),
            message: 'Role removed successfully.'
        );
    }
}

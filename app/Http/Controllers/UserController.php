<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $perPage = request()->integer('per_page', 20);
        $role = request()->string('role')->toString();

        $query = User::with(['roles:id,name', 'center:id,name']);

        if ($role !== '') {
            $query->whereHas('roles', fn($q) => $q->where('name', $role));
        }

        // Eager load role-specific relations for admin listings
        if ($role === 'student') {
            $query->with([
                'groups' => fn($q) => $q
                    ->with('center:id,name')
                    ->withCount('students')
            ]);
        }

        if ($role === 'parent') {
            $query->with([
                'children' => fn($q) => $q->with([
                    'groups' => fn($g) => $g
                        ->with('center:id,name')
                        ->withCount('students')
                ])
            ]);
        }

        $users = $query->orderBy('id')->paginate($perPage);

        return $this->success(
            data: $users,
            message: 'Users retrieved successfully.'
        );
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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')->where('guard_name', config('permission.defaults.guard'))],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ]);

        $roleName = $validated['role'] ?? null;
        unset($validated['role']);

        $validated['password'] = Hash::make($validated['password']);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = $path;
        }

        $user = User::create($validated);

        if (!empty($roleName)) {
            $user->assignRole($roleName);
        }

        return $this->success(
            data: $user->load('roles:id,name'),
            message: 'User created successfully.',
            status: 201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::with('roles:id,name')->findOrFail($id);

        return $this->success(
            data: $user,
            message: 'User retrieved successfully.'
        );
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
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')->where('guard_name', config('permission.defaults.guard'))],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ]);

        $roleName = $validated['role'] ?? null;
        unset($validated['role']);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = $path;
        }

        $user->update($validated);

        if (!empty($roleName)) {
            $user->syncRoles([$roleName]);
        }

        return $this->success(
            data: $user->load('roles:id,name'),
            message: 'User updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return $this->success(
            message: 'User deleted successfully.',
            status: 204
        );
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

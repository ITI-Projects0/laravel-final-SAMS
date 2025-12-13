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
        // return all users as JSON
        try {
            $perPage = max(1, min(request()->integer('per_page', 20), 200));
            $page = max(1, request()->integer('page', 1));
            $search = request()->string('search')->toString();
            $sortBy = request()->string('sort_by')->toString() ?: 'created_at';
            $sortDir = strtolower(request()->string('sort_dir')->toString()) === 'asc' ? 'asc' : 'desc';
            $allowedSorts = ['created_at', 'name', 'email', 'status', 'groups_count', 'children_count'];
            if (!in_array($sortBy, $allowedSorts)) {
                $sortBy = 'created_at';
            }

            $query = User::query()->with(['roles:id,name', 'center:id,name']);

            $role = null;
            if (request()->filled('role')) {
                $role = request()->string('role')->toString();

                $query->whereHas('roles', function ($q) use ($role) {
                    $q->where('name', $role);
                });
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            if ($role === 'student') {
                $query->withCount('groups')
                    ->with([
                        'groups:id,name,center_id',
                        'groups.center:id,name',
                    ]);
            }

            if ($role === 'parent') {
                $query->withCount('children')
                    ->with([
                        'children:id,name,email,center_id',
                        'children.groups:id,name,center_id',
                        'children.groups.center:id,name',
                    ]);
            }

            $users = $query
                ->orderBy($sortBy, $sortDir)
                ->paginate($perPage, ['*'], 'page', $page);
            return $this->success(
                data: $users,
                message: 'Users retrieved successfully.',
                meta: [
                    'pagination' => [
                        'current_page' => $users->currentPage(),
                        'per_page' => $users->perPage(),
                        'total' => $users->total(),
                        'last_page' => $users->lastPage(),
                    ],
                    'filters' => [
                        'search' => $search,
                        'role' => $role,
                        'sort_by' => $sortBy,
                        'sort_dir' => $sortDir,
                    ],
                ]
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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['required', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', 'string', 'max:50'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ]);

        $roleToAssign = $validated['role'] ?? null;
        unset($validated['role']);

        $validated['password'] = Hash::make($validated['password']);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = $path;
        }

        $user = User::create($validated);

        if (!empty($roleToAssign)) {
            $user->assignRole($roleToAssign);
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
            'role' => ['nullable', 'string', 'max:50'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ]);

        $roleToAssign = $validated['role'] ?? null;
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

        if (!empty($roleToAssign)) {
            $user->syncRoles([$roleToAssign]);
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

        // Revoke all tokens to force logout
        $user->tokens()->delete();

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

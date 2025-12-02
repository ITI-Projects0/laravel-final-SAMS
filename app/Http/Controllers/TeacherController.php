<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Resources\TeacherResource;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class TeacherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $teachers = User::role('teacher')
                ->with('taughtGroups.center')
                ->withCount('taughtGroups')
                ->paginate(15);
            return $this->success(
                data: $teachers,
                message: 'Teachers retrieved successfully.'
            );
        } catch (\Exception $e) {
            return $this->error(
                message: 'Failed to retrieve teachers.',
                status: 500,
                errors: $e->getMessage(),
            );
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $this->authorize('create', User::class);

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
                'phone' => ['nullable', 'string', 'max:20'],
            ]);

            $validated['role'] = 'teacher';
            $validated['password'] = Hash::make($validated['password']);

            $user = User::create($validated);
            $user->assignRole('teacher');

            return $this->success(
                data: $user,
                message: 'Teacher created successfully.'
            );
        } catch (\Exception $e) {
            return $this->error(
                message: 'Failed to create teacher.',
                status: 500,
                errors: $e->getMessage(),
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        try {
            if (!$user->hasRole('teacher') && $user->role !== 'teacher') {
                return $this->error(
                    message: 'This User Not Teacher',
                    status: 404
                );
            }

            $user->load(['taughtGroups.center', 'taughtGroups.students']);

            return $this->success(
                data: $user,
                message: 'Teacher retrieved successfully.'
            );
        } catch (\Exception $e) {
            return $this->error(
                message: 'Failed to retrieve teacher.',
                status: 500,
                errors: $e->getMessage(),
            );
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        try {
            $this->authorize('update', $user);
            $validated = $request->validate([
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'email' => [
                    'sometimes',
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($user->id),
                ],
                'phone' => ['nullable', 'string', 'max:20'],
            ]);

            $user->update($validated);

            return $this->success(
                data: $user,
                message: 'Teacher updated successfully.'
            );
        } catch (\Exception $e) {
            return $this->error(
                message: 'Failed to update teacher.',
                status: 500,
                errors: $e->getMessage(),
            );
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        try {
            $this->authorize('delete', $user);

            $user->delete();

            return $this->success(
                message: 'Teacher deleted successfully.',
                status: 204
            );
        } catch (\Exception $e) {
            return $this->error(
                message: 'Failed to delete teacher.',
                status: 500,
                errors: $e->getMessage(),
            );
        }
    }
}

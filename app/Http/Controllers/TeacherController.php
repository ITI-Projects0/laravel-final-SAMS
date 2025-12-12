<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeacherResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TeacherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->input('per_page', 15);
            $perPage = min(max($perPage, 5), 100);
            $search = $request->input('search');
            $sortBy = $request->input('sort_by', 'created_at');
            $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
            $allowedSorts = ['created_at', 'name', 'email', 'taught_groups_count', 'approved_students_count', 'pending_students_count'];
            if (!in_array($sortBy, $allowedSorts)) {
                $sortBy = 'created_at';
            }

            $user = Auth::user();
            $role = $request->input('role', 'teacher');
            if (!in_array($role, ['teacher', 'assistant'])) {
                $role = 'teacher';
            }

            $teachersQuery = User::role($role)
                ->with([
                    'center:id,name',
                    'taughtGroups' => function ($query) {
                        $query
                            ->with(['center:id,name'])
                            ->withCount([
                                'students',
                                'pendingStudents',
                                'lessons',
                                'attendances as attendance_today_count' => function ($q) {
                                    $q->whereDate('date', today());
                                },
                            ]);
                    }
                ])
                ->withCount([
                    'taughtGroups',
                    'taughtGroups as approved_students_count' => function ($q) {
                        $q->join('group_students', 'group_students.group_id', '=', 'groups.id')
                            ->where('group_students.status', 'approved')
                            ->selectRaw('count(distinct group_students.student_id)');
                    },
                    'taughtGroups as pending_students_count' => function ($q) {
                        $q->join('group_students', 'group_students.group_id', '=', 'groups.id')
                            ->where('group_students.status', 'pending')
                            ->selectRaw('count(distinct group_students.student_id)');
                    },
                ]);

            // Scope to center for non-admin users
            if ($user->hasAnyRole(['center_admin', 'assistant', 'teacher'])) {
                $centerId = $user->center_id ?? $user->ownedCenter?->id;
                if ($centerId) {
                    $teachersQuery->where('center_id', $centerId);
                } else {
                    // Safety: if no center assigned, show nothing
                    $teachersQuery->whereRaw('1 = 0');
                }
            }

            if ($search) {
                $teachersQuery->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            $teachers = $teachersQuery
                ->orderBy($sortBy, $sortDir)
                ->paginate($perPage);

            return $this->success(
                data: TeacherResource::collection($teachers),
                message: 'Teachers retrieved successfully.',
                meta: [
                    'pagination' => [
                        'current_page' => $teachers->currentPage(),
                        'per_page' => $teachers->perPage(),
                        'total' => $teachers->total(),
                        'last_page' => $teachers->lastPage(),
                    ],
                    'filters' => [
                        'search' => $search,
                        'sort_by' => $sortBy,
                        'sort_dir' => $sortDir,
                        'per_page' => $perPage,
                    ],
                ]
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
    public function store(\App\Http\Requests\StoreTeacherRequest $request)
    {
        try {
            $this->authorize('create', User::class);

            $validated = $request->validated();
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
            if (!$user->hasRole('teacher')) {
                return $this->error(
                    message: 'This User Not Teacher',
                    status: 404
                );
            }

            $user->load([
                'taughtGroups' => function ($query) {
                    $query
                        ->with(['center:id,name'])
                        ->withCount([
                            'students',
                            'pendingStudents',
                            'lessons',
                            'attendances as attendance_today_count' => function ($q) {
                                $q->whereDate('date', today());
                            },
                        ]);
                },
            ])
                ->loadCount([
                    'taughtGroups',
                    'taughtGroups as approved_students_count' => function ($q) {
                        $q->join('group_students', 'group_students.group_id', '=', 'groups.id')
                            ->where('group_students.status', 'approved')
                            ->selectRaw('count(distinct group_students.student_id)');
                    },
                    'taughtGroups as pending_students_count' => function ($q) {
                        $q->join('group_students', 'group_students.group_id', '=', 'groups.id')
                            ->where('group_students.status', 'pending')
                            ->selectRaw('count(distinct group_students.student_id)');
                    },
                ]);

            return $this->success(
                data: new TeacherResource($user),
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
    public function update(\App\Http\Requests\UpdateTeacherRequest $request, User $user)
    {
        try {
            $this->authorize('update', $user);

            $user->update($request->validated());

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

<?php

namespace App\Http\Controllers;

use App\Models\Center;
use App\Http\Requests\StoreCenterRequest;
use App\Http\Requests\UpdateCenterRequest;
use App\Http\Resources\CenterResource;
use Illuminate\Support\Facades\Auth;

class CenterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $perPage = max(5, min(request()->integer('per_page', 10), 100));
            $page = max(1, request()->integer('page', 1));
            $search = request()->string('search')->toString();
            $query = Center::query()->with('owner:id,name,email')->orderByDesc('updated_at');
            $isActiveFilter = null;

            if (request()->has('is_active')) {
                $isActiveFilter = filter_var(request()->get('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                if (!is_null($isActiveFilter)) {
                    $query->where('is_active', $isActiveFilter);
                }
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            }

            $centers = $query
                ->withCount('groups')
                ->paginate($perPage, ['*'], 'page', $page);

            return $this->success(
                data: CenterResource::collection($centers),
                message: 'Centers retrieved successfully.',
                meta: [
                    'pagination' => [
                        'current_page' => $centers->currentPage(),
                        'per_page' => $centers->perPage(),
                        'total' => $centers->total(),
                        'last_page' => $centers->lastPage(),
                    ],
                    'filters' => [
                        'is_active' => $isActiveFilter,
                        'search' => $search,
                    ],
                ]
            );
        } catch (\Exception $e) {
            return $this->error(
                message: 'Failed to retrieve centers.',
                status: 500,
                errors: $e->getMessage(),
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
    public function store(StoreCenterRequest $request)
    {
        try {
            $this->authorize('create', Center::class);

            $center = Center::create($request->validated());
            $center->load('owner');

            return $this->success(
                data: new CenterResource($center),
                message: 'Center created successfully.',
                status: 201
            );
        } catch (\Exception $e) {
            return $this->error(
                message: 'Failed to create center.',
                status: 500,
                errors: $e->getMessage(),
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Center $center)
    {
        $center->load('owner');

        return $this->success(
            data: new CenterResource($center),
            message: 'Center retrieved successfully.'
        );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Center $center)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCenterRequest $request, Center $center)
    {
        try {
            $this->authorize('update', $center);

            $center->update($request->validated());
            $center->load('owner');

            return $this->success(
                data: new CenterResource($center),
                message: 'Center updated successfully.'
            );
        } catch (\Exception $e) {
            return $this->error(
                message: 'Failed to update center.',
                status: 500,
                errors: $e->getMessage(),
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Center $center)
    {
        try {
            $this->authorize('delete', $center);

            $center->delete();

            return $this->success(
                message: 'Center deleted successfully.',
                status: 204
            );
        } catch (\Exception $e) {
            return $this->error(
                message: 'Failed to delete center.',
                status: 500,
                errors: $e->getMessage(),
            );
        }
    }

    /**
     * Toggle center active status (admin only).
     */
    public function toggleStatus(Center $center)
    {
        try {
            $this->authorize('update', $center);

            $center->is_active = !$center->is_active;
            $center->save();
            $center->load('owner');

            return $this->success(
                data: new CenterResource($center),
                message: 'Center status updated successfully.'
            );
        } catch (\Exception $e) {
            return $this->error(
                message: 'Failed to update center status.',
                status: 500,
                errors: $e->getMessage(),
            );
        }
    }
}

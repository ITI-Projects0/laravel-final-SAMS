<?php

namespace App\Http\Controllers;

use App\Models\Center;
use App\Http\Requests\StoreCenterRequest;
use App\Http\Requests\UpdateCenterRequest;
use Illuminate\Support\Facades\Auth;

class CenterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $centers = Center::query()
                ->with('owner:id,name,email')
                ->paginate(15);

            return $this->success(
                data: $centers,
                message: 'Centers retrieved successfully.'
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

            return $this->success(
                data: $center,
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
        return $this->success(
            data: $center->load('owner:id,name,email'),
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

            return $this->success(
                data: $center,
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
}

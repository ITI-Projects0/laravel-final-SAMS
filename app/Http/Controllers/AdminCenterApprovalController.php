<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Center;
use App\Notifications\CenterAdminStatusChanged;
use Illuminate\Http\Request;

/**
 * Controller for managing center admin approval workflow.
 * Only accessible by admin users.
 */
class AdminCenterApprovalController extends Controller
{
    /**
     * List all pending center admin registrations.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $pendingUsers = User::where('approval_status', 'pending')
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'center_admin');
                })
                ->with(['center', 'roles'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return $this->success(
                data: UserResource::collection($pendingUsers),
                message: 'Pending center admins retrieved successfully.'
            );
        } catch (\Exception $e) {
            return $this->error(
                message: 'Failed to retrieve pending center admins.',
                status: 500,
                errors: config('app.debug') ? $e->getMessage() : null
            );
        }
    }

    /**
     * Approve a pending center admin.
     *
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function approve(User $user)
    {
        try {
            if (!$user->hasRole('center_admin')) {
                return $this->error('User is not a center admin.', 400);
            }

            if ($user->approval_status !== 'pending') {
                return $this->error('User is not pending approval.', 400);
            }

            $user->approval_status = 'approved';
            $user->save();

            // Activate the center as well
            if ($user->ownedCenter) {
                $user->ownedCenter->is_active = true;
                $user->ownedCenter->save();
            }

            // Send notification to the user (email + database)
            $user->notify(new CenterAdminStatusChanged('approved'));

            $user->load(['ownedCenter', 'roles']);

            return $this->success(
                data: new UserResource($user),
                message: 'Center admin approved successfully.'
            );
        } catch (\Exception $e) {
            return $this->error(
                message: 'Failed to approve center admin.',
                status: 500,
                errors: config('app.debug') ? $e->getMessage() : null
            );
        }
    }

    /**
     * Reject a pending center admin.
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function reject(Request $request, User $user)
    {
        try {
            $validated = $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);

            if (!$user->hasRole('center_admin')) {
                return $this->error('User is not a center admin.', 400);
            }

            if ($user->approval_status !== 'pending') {
                return $this->error('User is not pending approval.', 400);
            }

            $user->approval_status = 'rejected';
            $user->save();

            // Keep center inactive
            if ($user->ownedCenter) {
                $user->ownedCenter->is_active = false;
                $user->ownedCenter->save();
            }

            // Send notification to the user (email + database)
            $reason = $validated['reason'] ?? null;
            $user->notify(new CenterAdminStatusChanged('rejected', $reason));

            $user->load(['ownedCenter', 'roles']);

            return $this->success(
                data: new UserResource($user),
                message: 'Center admin rejected successfully.'
            );
        } catch (\Exception $e) {
            return $this->error(
                message: 'Failed to reject center admin.',
                status: 500,
                errors: config('app.debug') ? $e->getMessage() : null
            );
        }
    }
}

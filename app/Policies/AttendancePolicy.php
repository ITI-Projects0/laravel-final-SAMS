<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AttendancePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'center_admin', 'teacher', 'assistant']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Attendance $attendance): bool
    {
        return $this->canAccessAttendance($user, $attendance);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canAccessCenter($user, $attendanceCenterId = null);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Attendance $attendance): bool
    {
        return $this->canAccessAttendance($user, $attendance);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Attendance $attendance): bool
    {
        return $this->canAccessAttendance($user, $attendance);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Attendance $attendance): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Attendance $attendance): bool
    {
        return false;
    }

    protected function canAccessAttendance(User $user, Attendance $attendance): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $centerId = $attendance->center_id;
        return $this->canAccessCenter($user, $centerId);
    }

    protected function canAccessCenter(User $user, ?int $centerId): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (!$centerId) {
            return false;
        }

        // Center admin of this center
        if ($user->hasRole('center_admin')) {
            return $user->center?->id === $centerId;
        }

        // Teacher/assistant who teaches/works in this center via groups
        if ($user->hasAnyRole(['teacher', 'assistant'])) {
            return $user->taughtGroups()->where('center_id', $centerId)->exists()
                || $user->groups()->where('center_id', $centerId)->exists();
        }

        return false;
    }
}

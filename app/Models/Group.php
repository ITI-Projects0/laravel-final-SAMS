<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'join_code',
        'subject',
        'center_id',
        'teacher_id',
        'is_approval_required',
        'is_active',
    ];

    protected $casts = [
        'is_approval_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function center()
    {
        return $this->belongsTo(Center::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'teacher')
                    ->where('guard_name', config('permission.defaults.guard'));
            });
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'group_students', 'group_id', 'student_id')
            ->withPivot('status', 'joined_at')
            ->where('group_students.status', 'approved')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'student')
                    ->where('guard_name', config('permission.defaults.guard'));
            })
            ->withTimestamps();
    }

    public function pendingStudents()
    {
        return $this->belongsToMany(User::class, 'group_students', 'group_id', 'student_id')
            ->withPivot('status', 'joined_at')
            ->where('group_students.status', 'pending')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'student')
                    ->where('guard_name', config('permission.defaults.guard'));
            })
            ->withTimestamps();
    }

    public function assessments()
    {
        return $this->hasMany(Assessment::class);
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class);
    }
}

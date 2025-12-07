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
        'subject',
        'center_id',
        'teacher_id',
        'academic_year',
        'schedule_days',
        'schedule_time',
        'sessions_count',
        'is_active',
    ];

    protected $casts = [
        // 'is_approval_required' => 'boolean',
        'is_active' => 'boolean',
        'schedule_days' => 'array',
        'schedule_time' => 'datetime:H:i',
    ];

    public function center()
    {
        return $this->belongsTo(Center::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'group_students', 'group_id', 'student_id')
            ->withPivot('status', 'joined_at', 'is_pay')
            ->where('group_students.status', 'approved')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'student');
            })
            ->withTimestamps();
    }

    public function pendingStudents()
    {
        return $this->belongsToMany(User::class, 'group_students', 'group_id', 'student_id')
            ->withPivot('status', 'joined_at', 'is_pay')
            ->where('group_students.status', 'pending')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'student');
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

<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'status',
        'approval_status',
        'activation_code',
        'google_id',
        'center_id',
        'avatar', // Maybe make error
    ];
    protected $guard_name = 'api';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'status' => 'string',
        'approval_status' => 'string',
    ];

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isCenterAdmin(): bool
    {
        return $this->hasRole('center_admin');
    }

    public function isTeacher(): bool
    {
        return $this->hasRole('teacher');
    }

    public function isAssistant(): bool
    {
        return $this->hasRole('assistant');
    }

    public function isStudent(): bool
    {
        return $this->hasRole('student');
    }

    public function isParent(): bool
    {
        return $this->hasRole('parent');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPending(): bool
    {
        return $this->approval_status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->approval_status === 'rejected';
    }

    public function taughtGroups()
    {
        return $this->hasMany(Group::class, 'teacher_id');
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_students', 'student_id', 'group_id')
            ->withTimestamps();
    }

    public function center()
    {
        return $this->belongsTo(Center::class);
    }

    public function ownedCenter()
    {
        return $this->hasOne(Center::class, 'user_id');
    }

    public function children()
    {
        return $this->belongsToMany(User::class, 'parent_student_links', 'parent_id', 'student_id')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    public function parents()
    {
        return $this->belongsToMany(User::class, 'parent_student_links', 'student_id', 'parent_id')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    public function courses()
    {
        return $this->belongsToMany(Group::class, 'group_students', 'student_id', 'group_id')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    public function assessments()
    {
        return $this->hasManyThrough(
            Assessment::class,
            GroupStudent::class,
            'student_id',
            'group_id',
            'id',
            'group_id'
        );
    }

    public function assignments()
    {
        return $this->assessments();
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'student_id');
    }

    public function presentLessons()
    {
        return $this->attendances()->where('status', 'present');
    }
}

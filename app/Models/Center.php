<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Center extends Model
{
    /** @use HasFactory<\Database\Factories\CenterFactory> */
    use HasFactory;
    protected $fillable = [
        'user_id',
        'name',
        'logo_url',
        'primary_color',
        'secondary_color',
        'subdomain',
        'is_active',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function groups()
    {
        return $this->hasMany(Group::class);
    }
}

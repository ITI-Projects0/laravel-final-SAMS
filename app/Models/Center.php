<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Center extends Model
{
    /** @use HasFactory<\Database\Factories\CenterFactory> */
    use HasFactory;
    protected $fillable = ['name', 'address', 'phone'];

    public function center()
    {
        return $this->hasMany(User::class);
    }
}

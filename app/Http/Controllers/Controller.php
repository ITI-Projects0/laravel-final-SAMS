<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use ApiResponse, AuthorizesRequests;

    protected function frontendLoginUrl(): string
    {
        $baseUrl = config('app.frontend_url', 'http://localhost:4200');
        return rtrim($baseUrl, '/') . '/login';
    }
}

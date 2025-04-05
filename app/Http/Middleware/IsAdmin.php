<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Import Auth facade
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated AND has the 'admin' role
        // Assumes your User model has a 'role' attribute/column
        if (Auth::check() && Auth::user()->role === 'admin') {
            return $next($request); // User is admin, proceed
        }

        // User is not authenticated or not an admin
        return response()->json(['message' => 'Unauthorized. Admin access required.'], 403); // Forbidden
    }
}
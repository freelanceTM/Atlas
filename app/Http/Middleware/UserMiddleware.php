<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UserMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            if (auth()->user()->is_suspended == 1) {
                Auth::logout();
                return redirect()->route('login')->with('error', 'Your account is temporarily suspended');
            }

            // UNIFIED ACCESS CONTROL (Spatie): the legacy `type` column does not
            // exist on the users table. This area is for regular (non-Admin) users,
            // so block anyone holding the Admin role using the single Spatie mechanism.
            if (auth()->user()->hasRole('Admin')) {
                Auth::logout();
                return redirect()->route('login')->with('error', 'You are not a User');
            }

            return $next($request);
        } else {
            return redirect()->route('login')->with('error', 'You are not logged in');
        }
    }
}

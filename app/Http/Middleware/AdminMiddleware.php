<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * FIX: Добавлена проверка is_suspended.
     * Риск: заблокированный администратор мог продолжать работать
     * до тех пор, пока его сессия не истекала.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route("login")->with("error", "You are not logged in");
        }

        $user = Auth::user();

        // FIX: проверка suspended — до проверки роли, чтобы заблокировать любого
        if ($user->is_suspended) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route("login")->with("error", "Your account has been suspended. Contact an administrator.");
        }

        if (!$user->hasRole("Admin")) {
            abort(403, "Access denied. Admin role required.");
        }

        return $next($request);
    }
}

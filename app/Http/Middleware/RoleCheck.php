<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class RoleCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $role): Response
    {
        $user = Auth::user();
        if ($user && $user->role === 'admin') {
            return $next($request);
        }
        if ($user && $user->role === 'instructor' && in_array($role, ['instructor', 'student'])) {
            return $next($request);
        }
        if ($user && $user->role === 'student' && $role === 'student') {
            return $next($request);
        }
        return formatResponse(STATUS_FAIL, '', '', __('messages.no_permission'));
    }

}

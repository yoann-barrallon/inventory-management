<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckInventoryAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        if (!$user) {
            abort(403, 'Authentication required');
        }

        // Check if user has access to inventory system
        $allowedRoles = ['admin', 'stock_manager', 'operator'];

        if (!$user->hasAnyRole($allowedRoles)) {
            abort(403, 'Access denied - You do not have permission to access the inventory system');
        }

        // Check if user has basic dashboard permission
        if (!$user->can('view dashboard')) {
            abort(403, 'Access denied - Dashboard access required');
        }

        return $next($request);
    }
}

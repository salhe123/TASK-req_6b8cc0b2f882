<?php
declare(strict_types=1);

namespace app\middleware;

use think\Request;
use think\Response;

class RbacMiddleware
{
    /**
     * @param Request  $request
     * @param \Closure $next
     * @param string   $requiredRole  Comma-separated roles (e.g., "SYSTEM_ADMIN,FINANCE_CLERK")
     */
    public function handle(Request $request, \Closure $next, string $requiredRole = ''): Response
    {
        $user = session('user');

        if (!$user) {
            return json_error('Unauthorized', 401);
        }

        // SYSTEM_ADMIN always has access
        if ($user['role'] === 'SYSTEM_ADMIN') {
            return $next($request);
        }

        // Check if user's role matches any of the required roles
        $allowedRoles = array_map('trim', explode(',', $requiredRole));
        if (!in_array($user['role'], $allowedRoles, true)) {
            return json_error('Access denied (insufficient role)', 403);
        }

        return $next($request);
    }
}

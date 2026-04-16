<?php
declare(strict_types=1);

namespace app\middleware;

use think\Request;
use think\Response;

class AuthMiddleware
{
    /**
     * @param string $requiredRole  Optional role for RBAC (e.g., 'SYSTEM_ADMIN')
     */
    public function handle(Request $request, \Closure $next, string $requiredRole = ''): Response
    {
        $user = session('user');

        if (!$user) {
            return json_error('Unauthorized / session expired', 401);
        }

        // Check 15-minute idle timeout
        $lastActivity = session('last_activity');
        if ($lastActivity && (time() - $lastActivity) > 900) {
            session(null);
            return json_error('Session expired due to inactivity', 401);
        }

        // Update last activity timestamp
        session('last_activity', time());

        // RBAC check (if a role is required)
        if ($requiredRole !== '') {
            // SYSTEM_ADMIN always has access
            if ($user['role'] !== 'SYSTEM_ADMIN') {
                $allowedRoles = array_map('trim', explode(',', $requiredRole));
                if (!in_array($user['role'], $allowedRoles, true)) {
                    return json_error('Access denied (insufficient role)', 403);
                }
            }
        }

        // Attach user to request
        $request->user = $user;

        return $next($request);
    }
}

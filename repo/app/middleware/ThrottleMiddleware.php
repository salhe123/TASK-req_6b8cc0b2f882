<?php
declare(strict_types=1);

namespace app\middleware;

use think\Request;
use think\Response;
use think\facade\Cache;

/**
 * Per-user request-rate throttling.
 *
 * Two distinct limits are enforced from the `pp_throttle_config` table (cached in memory
 * with a 60-second TTL to avoid thundering-herd DB reads):
 *
 *   - requests_per_minute  (default 60)  : global cap applied to any authenticated request.
 *   - appointments_per_hour (default 10) : applied only when the POST hits /api/appointments.
 *
 * Unauthenticated requests pass through unchanged — those are rate-limited by the webserver.
 */
class ThrottleMiddleware
{
    public function handle(Request $request, \Closure $next, string $scope = 'rpm'): Response
    {
        $user = session('user');
        if (!$user) {
            return $next($request);
        }

        $userId = $user['id'];

        if ($scope === 'appointments_per_hour') {
            $limit = $this->getLimit('appointments_per_hour', 10);
            $key   = 'throttle:appt_hour:' . $userId;
            $ttl   = 3600;
            $label = 'Appointment creation throttle exceeded (per-hour cap)';
        } else {
            $limit = $this->getLimit('requests_per_minute', 60);
            $key   = 'throttle:rpm:' . $userId;
            $ttl   = 60;
            $label = 'Throttle limit exceeded';
        }

        $count = (int) Cache::get($key, 0);
        if ($count >= $limit) {
            return json_error($label, 429);
        }
        Cache::set($key, $count + 1, $ttl);

        return $next($request);
    }

    private function getLimit(string $key, int $default): int
    {
        try {
            $cacheKey = 'throttle:cfg:' . $key;
            $cached   = Cache::get($cacheKey);
            if ($cached !== null && $cached !== false) {
                return (int) $cached;
            }
            $val = \think\facade\Db::name('throttle_config')
                ->where('key', $key)
                ->value('value');
            $limit = $val ? (int) $val : $default;
            Cache::set($cacheKey, $limit, 60);
            return $limit;
        } catch (\Exception $e) {
            return $default;
        }
    }
}

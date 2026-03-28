<?php

namespace App\Http\Middleware;

use App\Domain\Security\Models\AdminIpAllowlist;
use App\Domain\Security\Models\AdminIpBlocklist;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        // Check blocklist first — blocked IPs are always rejected (cached 60s)
        $blockedIps = Cache::remember('admin_ip_blocklist', 60, function () {
            return AdminIpBlocklist::pluck('ip_address')->all();
        });

        if (in_array($ip, $blockedIps, true)) {
            abort(403, __('security.ip_blocked'));
        }

        // Check allowlist — if any entries exist, only those IPs are allowed (cached 60s)
        $allowedIps = Cache::remember('admin_ip_allowlist', 60, function () {
            return AdminIpAllowlist::pluck('ip_address')->all();
        });

        if (count($allowedIps) > 0 && ! in_array($ip, $allowedIps, true)) {
            abort(403, __('security.ip_not_allowed'));
        }

        return $next($request);
    }
}

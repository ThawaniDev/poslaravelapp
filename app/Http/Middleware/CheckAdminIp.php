<?php

namespace App\Http\Middleware;

use App\Domain\Security\Models\AdminIpAllowlist;
use App\Domain\Security\Models\AdminIpBlocklist;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        // Check blocklist first — blocked IPs are always rejected
        $isBlocked = AdminIpBlocklist::where('ip_address', $ip)->exists();

        if ($isBlocked) {
            abort(403, __('security.ip_blocked'));
        }

        // Check allowlist — if any entries exist, only those IPs are allowed
        $allowlistCount = AdminIpAllowlist::count();

        if ($allowlistCount > 0) {
            $isAllowed = AdminIpAllowlist::where('ip_address', $ip)->exists();

            if (! $isAllowed) {
                abort(403, __('security.ip_not_allowed'));
            }
        }

        return $next($request);
    }
}

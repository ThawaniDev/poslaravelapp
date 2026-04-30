<?php

namespace App\Http\Middleware;

use App\Domain\Security\Models\AdminIpAllowlist;
use App\Domain\Security\Models\AdminIpBlocklist;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminIp
{
    /**
     * Cache TTL in seconds.  Short enough to react quickly to changes,
     * long enough to avoid a DB query on every request.
     */
    private const CACHE_TTL = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip() ?? '0.0.0.0';

        // ── 1. Blocklist check (runs before allowlist, always) ────────────
        $blockedEntries = Cache::remember('admin_ip_blocklist_v2', self::CACHE_TTL, function () {
            return AdminIpBlocklist::where(function ($q) {
                // Only non-expired entries
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })->get(['id', 'ip_address', 'is_cidr'])->toArray();
        });

        foreach ($blockedEntries as $entry) {
            if ($this->matches($ip, $entry['ip_address'], (bool) ($entry['is_cidr'] ?? false))) {
                // Increment hit_count + last_hit_at outside the cache
                $this->recordBlockHit($entry['id']);
                abort(403, __('security.ip_blocked'));
            }
        }

        // ── 2. Allowlist check (enforced only when the list is non-empty) ─
        $allowedEntries = Cache::remember('admin_ip_allowlist_v2', self::CACHE_TTL, function () {
            return AdminIpAllowlist::where(function ($q) {
                // Only non-expired entries
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })->get(['ip_address', 'is_cidr'])->toArray();
        });

        if (count($allowedEntries) > 0) {
            $allowed = false;
            foreach ($allowedEntries as $entry) {
                if ($this->matches($ip, $entry['ip_address'], (bool) ($entry['is_cidr'] ?? false))) {
                    $allowed = true;
                    break;
                }
            }

            if (! $allowed) {
                abort(403, __('security.ip_not_allowed'));
            }
        }

        return $next($request);
    }

    /**
     * Test whether $clientIp matches $entry (either exact string or CIDR range).
     */
    private function matches(string $clientIp, string $entry, bool $isCidr): bool
    {
        if (! $isCidr) {
            return $clientIp === $entry;
        }

        return $this->ipInCidr($clientIp, $entry);
    }

    /**
     * Test whether an IP address falls inside a CIDR range.
     * Supports both IPv4 (e.g. 10.0.0.0/8) and IPv6 (e.g. 2001:db8::/32).
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$network, $prefixLen] = explode('/', $cidr, 2);
        $prefixLen = (int) $prefixLen;

        // Detect address family
        $ipBin      = @inet_pton($ip);
        $networkBin = @inet_pton($network);

        if ($ipBin === false || $networkBin === false) {
            return false;
        }

        // Both must be the same address family
        if (strlen($ipBin) !== strlen($networkBin)) {
            return false;
        }

        $bytes = strlen($ipBin); // 4 for IPv4, 16 for IPv6
        $bits  = $bytes * 8;

        if ($prefixLen < 0 || $prefixLen > $bits) {
            return false;
        }

        // Build the mask as a packed binary string
        $fullBytes  = (int) ($prefixLen / 8);
        $remainder  = $prefixLen % 8;
        $mask       = str_repeat("\xff", $fullBytes);

        if ($remainder > 0) {
            $mask .= chr((0xff << (8 - $remainder)) & 0xff);
        }

        $mask = str_pad($mask, $bytes, "\x00");

        return ($ipBin & $mask) === ($networkBin & $mask);
    }

    /**
     * Increment hit_count and last_hit_at for a blocklist entry.
     * Uses a raw query to avoid loading the model (performance).
     */
    private function recordBlockHit(string $entryId): void
    {
        try {
            DB::table('admin_ip_blocklist')
                ->where('id', $entryId)
                ->update([
                    'hit_count'   => DB::raw('hit_count + 1'),
                    'last_hit_at' => now(),
                ]);
            // Bust the cache so the updated count is visible on the next Filament page load
            Cache::forget('admin_ip_blocklist_v2');
        } catch (\Throwable) {
            // Non-critical – do not abort the request if logging fails
        }
    }
}

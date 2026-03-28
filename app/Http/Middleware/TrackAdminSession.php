<?php

namespace App\Http\Middleware;

use App\Domain\Security\Models\AdminSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TrackAdminSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('admin')->user();

        if (! $admin) {
            return $next($request);
        }

        // Auto-expire stale sessions once per 5 minutes
        $this->expireStaleSessions();

        $laravelSessionId = $request->session()->getId();
        $sessionHash = hash('sha256', $laravelSessionId);
        $cacheKey = "admin_session:{$sessionHash}";

        // Cache the session lookup for 60 seconds to avoid a DB query per request
        $sessionId = Cache::get($cacheKey);

        if (! $sessionId) {
            $session = AdminSession::where('session_token_hash', $sessionHash)->first();

            if (! $session) {
                $session = AdminSession::create([
                    'admin_user_id' => $admin->id,
                    'session_token_hash' => $sessionHash,
                    'ip_address' => $request->ip() ?? '0.0.0.0',
                    'user_agent' => $request->userAgent() ?? '',
                    'status' => 'active',
                    'two_fa_verified' => false,
                    'started_at' => now(),
                    'last_activity_at' => now(),
                    'expires_at' => now()->addHours(24),
                ]);
            } else {
                // Throttle updates to every 60 seconds to avoid DB writes on every request
                if (! $session->last_activity_at || $session->last_activity_at->diffInSeconds(now()) >= 60) {
                    $session->update([
                        'last_activity_at' => now(),
                        'ip_address' => $request->ip() ?? '0.0.0.0',
                    ]);
                }
            }

            Cache::put($cacheKey, $session->id, 60);
        }

        return $next($request);
    }

    /**
     * Mark sessions as expired if they are past their expires_at or
     * have had no activity for over 24 hours. Runs at most once per 5 minutes.
     */
    private function expireStaleSessions(): void
    {
        $cacheKey = 'admin_sessions:stale_check';

        if (Cache::has($cacheKey)) {
            return;
        }

        AdminSession::where('status', 'active')
            ->where(function ($query) {
                $query->where('expires_at', '<', now())
                    ->orWhere('last_activity_at', '<', now()->subHours(24));
            })
            ->update([
                'status' => 'expired',
                'ended_at' => now(),
            ]);

        Cache::put($cacheKey, true, 300); // 5 minutes
    }
}

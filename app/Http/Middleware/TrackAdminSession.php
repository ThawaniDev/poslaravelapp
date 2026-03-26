<?php

namespace App\Http\Middleware;

use App\Domain\Security\Models\AdminSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackAdminSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('admin')->user();

        if (! $admin) {
            return $next($request);
        }

        $laravelSessionId = $request->session()->getId();
        $sessionHash = hash('sha256', $laravelSessionId);

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

        return $next($request);
    }
}

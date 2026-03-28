<?php

namespace App\Http\Responses;

use App\Domain\Security\Models\AdminSession;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class AdminLogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        // Mark the current admin session as ended
        $laravelSessionId = $request->session()->getId();
        $sessionHash = hash('sha256', $laravelSessionId);

        AdminSession::where('session_token_hash', $sessionHash)
            ->where('status', 'active')
            ->update([
                'status' => 'expired',
                'ended_at' => now(),
            ]);

        // Clear the cached session lookup
        Cache::forget("admin_session:{$sessionHash}");

        return redirect()->to(filament()->getLoginUrl());
    }
}

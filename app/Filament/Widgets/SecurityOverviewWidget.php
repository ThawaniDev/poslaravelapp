<?php

namespace App\Filament\Widgets;

use App\Domain\Security\Models\AdminSession;
use App\Domain\Security\Models\AdminTrustedDevice;
use App\Domain\Security\Models\LoginAttempt;
use App\Domain\Security\Models\SecurityAlert;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Number;

class SecurityOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    public function getHeading(): ?string
    {
        return __('security.security_overview');
    }

    public static function canView(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['security.view', 'security.manage_alerts']);
    }

    protected function getStats(): array
    {
        $data = Cache::remember('filament:security_overview', 60, function () {
            return [
                'newAlerts' => SecurityAlert::new()->count(),
                'criticalAlerts' => SecurityAlert::unresolved()->critical()->count(),
                'failedLogins24h' => LoginAttempt::failed()
                    ->where('attempted_at', '>=', now()->subHours(24))
                    ->count(),
                'activeSessions' => AdminSession::active()->count(),
                'trustedDevices' => AdminTrustedDevice::count(),
                'unresolvedAlerts' => SecurityAlert::unresolved()->count(),
            ];
        });

        return [
            Stat::make(__('security.new_alerts'), Number::format($data['newAlerts']))
                ->description(__('security.unresolved_count', ['count' => $data['unresolvedAlerts']]))
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($data['newAlerts'] > 0 ? 'danger' : 'success')
                ->chart([7, 3, 4, 5, $data['newAlerts']]),

            Stat::make(__('security.critical_alerts'), Number::format($data['criticalAlerts']))
                ->description(__('security.require_immediate_action'))
                ->descriptionIcon('heroicon-m-fire')
                ->color($data['criticalAlerts'] > 0 ? 'danger' : 'success'),

            Stat::make(__('security.failed_logins_24h'), Number::format($data['failedLogins24h']))
                ->description(__('security.brute_force_monitoring'))
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color($data['failedLogins24h'] > 20 ? 'danger' : ($data['failedLogins24h'] > 5 ? 'warning' : 'success')),

            Stat::make(__('security.active_sessions'), Number::format($data['activeSessions']))
                ->description(__('security.currently_online'))
                ->descriptionIcon('heroicon-m-signal')
                ->color('info'),

            Stat::make(__('security.trusted_devices'), Number::format($data['trustedDevices']))
                ->description(__('security.registered_devices'))
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->color('primary'),
        ];
    }
}

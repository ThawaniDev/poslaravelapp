<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('subscription.email_reminder_title') }}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        .wrapper { width: 100%; background-color: #f4f4f7; padding: 24px 0; }
        .container { max-width: 570px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); padding: 24px 32px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 20px; font-weight: 600; }
        .content { padding: 32px; color: #333333; line-height: 1.6; font-size: 15px; }
        .content h2 { margin: 0 0 16px; font-size: 18px; color: #1a1a2e; }
        .alert { padding: 16px; border-radius: 8px; margin: 16px 0; }
        .alert-warning { background: #fef3c7; border-left: 4px solid #f59e0b; }
        .alert-danger { background: #fee2e2; border-left: 4px solid #ef4444; }
        .alert-info { background: #dbeafe; border-left: 4px solid #3b82f6; }
        .detail { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .detail-label { color: #666; font-size: 14px; }
        .detail-value { font-weight: 600; color: #1a1a2e; font-size: 14px; }
        .footer { padding: 20px 32px; text-align: center; font-size: 12px; color: #999999; border-top: 1px solid #eeeeee; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="container">
        <div class="header">
            <h1>{{ __('subscription.email_brand_name') }}</h1>
        </div>
        <div class="content">
            @if($reminderType === 'upcoming')
                <h2>{{ __('subscription.email_upcoming_heading') }}</h2>
                <div class="alert alert-warning">
                    {{ __('subscription.email_upcoming_body', ['plan' => $planName, 'date' => $expiryDate]) }}
                </div>
            @elseif($reminderType === 'overdue')
                <h2>{{ __('subscription.email_overdue_heading') }}</h2>
                <div class="alert alert-danger">
                    {{ __('subscription.email_overdue_body', ['plan' => $planName]) }}
                </div>
            @elseif($reminderType === 'trial_ending')
                <h2>{{ __('subscription.email_trial_heading') }}</h2>
                <div class="alert alert-info">
                    {{ __('subscription.email_trial_body', ['plan' => $planName, 'date' => $expiryDate]) }}
                </div>
            @endif

            <table style="width: 100%; margin-top: 20px; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #f0f0f0; color: #666; font-size: 14px;">{{ __('subscription.email_label_organization') }}</td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-weight: 600; color: #1a1a2e; font-size: 14px; text-align: right;">{{ $organizationName }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #f0f0f0; color: #666; font-size: 14px;">{{ __('subscription.email_label_plan') }}</td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-weight: 600; color: #1a1a2e; font-size: 14px; text-align: right;">{{ $planName }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #666; font-size: 14px;">{{ __('subscription.email_label_expiry_date') }}</td>
                    <td style="padding: 8px 0; font-weight: 600; color: #1a1a2e; font-size: 14px; text-align: right;">{{ $expiryDate }}</td>
                </tr>
            </table>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} {{ __('subscription.email_brand_name') }}. {{ __('subscription.email_footer_rights') }}
        </div>
    </div>
</div>
</body>
</html>

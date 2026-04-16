<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Reminder</title>
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
            <h1>Wameed POS</h1>
        </div>
        <div class="content">
            @if($reminderType === 'upcoming')
                <h2>Your subscription is expiring soon</h2>
                <div class="alert alert-warning">
                    Your <strong>{{ $planName }}</strong> plan expires on <strong>{{ $expiryDate }}</strong>. Please renew to avoid service interruption.
                </div>
            @elseif($reminderType === 'overdue')
                <h2>Your subscription has expired</h2>
                <div class="alert alert-danger">
                    Your <strong>{{ $planName }}</strong> plan expired. Please renew immediately to restore full access.
                </div>
            @elseif($reminderType === 'trial_ending')
                <h2>Your trial is ending soon</h2>
                <div class="alert alert-info">
                    Your trial for <strong>{{ $planName }}</strong> ends on <strong>{{ $expiryDate }}</strong>. Subscribe now to keep all features.
                </div>
            @endif

            <table style="width: 100%; margin-top: 20px; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #f0f0f0; color: #666; font-size: 14px;">Organization</td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-weight: 600; color: #1a1a2e; font-size: 14px; text-align: right;">{{ $organizationName }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #f0f0f0; color: #666; font-size: 14px;">Plan</td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-weight: 600; color: #1a1a2e; font-size: 14px; text-align: right;">{{ $planName }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #666; font-size: 14px;">Expiry Date</td>
                    <td style="padding: 8px 0; font-weight: 600; color: #1a1a2e; font-size: 14px; text-align: right;">{{ $expiryDate }}</td>
                </tr>
            </table>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Wameed POS. All rights reserved.
        </div>
    </div>
</div>
</body>
</html>
